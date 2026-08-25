<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    use ApiResponse;
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::find($validated['order_id']);


        if ($order->client_id !== auth()->id()) {
            return $this->errorResponse(
                'You are not authorized to pay for this order',
                403
            );
        }



        if ($order->payment_method !== 'card') {
            return $this->errorResponse(
                'This order does not use electronic payment',
                422
            );
        }



        if ($order->payment_status !== 'pending') {
            return $this->errorResponse(
                'This order is not available for payment',
                422
            );
        }


        if (in_array($order->status, ['canceled', 'completed', 'in_process'])) {
            return $this->errorResponse(
                'This order cannot be paid',
                422
            );
        }



        try {
            $stripe = new StripeClient(
                config('services.stripe.secret')
            );



            $amount = (int) round($order->total_price * 100);

            if ($amount <= 0) {
                return $this->errorResponse(
                    'Invalid order amount',
                    422
                );
            }


            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $amount,
                'currency' => 'usd',
                'capture_method' => 'manual',
                'payment_method_types' => ['card'],

                'metadata' => [
                    'order_id' => (string) $order->id,
                    'client_id' => (string) $order->client_id,
                    'package_id' => (string) $order->package_id,
                ],
            ]);



            $order->update([
                'stripe_payment_intent_id' => $paymentIntent->id,
            ]);



            return $this->successResponse(
                [
                    'order_id' => $order->id,
                    'amount' => $amount,
                    'currency' => 'usd',
                    'payment_intent_id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                ],
                'Payment intent created successfully',
                200
            );

        } catch (\Stripe\Exception\ApiErrorException $e) {

            return $this->errorResponse(
                'Unable to create payment intent: ' . $e->getMessage(),
                500
            );

        } catch (\Exception $e) {

            return $this->errorResponse(
                'An unexpected error occurred while creating the payment',
                500
            );
        }
    }

    // ==========================================
    // SECTION 1: CLIENT APIs
    // ==========================================


    public function clientPayments(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Payment::where('user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $perPage = $request->get('per_page', 10);
        $orders = $query->latest()->paginate($perPage);

        return $this->successResponse($orders, 'Client payments retrieved successfully', 200);
    }


    public function clientShowPayment($id): JsonResponse
    {
        $user = auth()->user();
        $order = Order::with(['package.service.company'])
            ->where('client_id', $user->id)
            ->find($id);

        if (!$order) {
            return $this->errorResponse('Payment not found', 404);
        }

        return $this->successResponse([
            'id' => $order->id,
            'order' => [
                'id' => $order->id,
                'status' => $order->status
            ],
            'company' => [
                'id' => $order->package->service->company->id ?? null,
                'name' => $order->package->service->company->name ?? '',
            ],
            'service' => [
                'id' => $order->package->service->id ?? null,
                'name' => $order->package->service->name ?? '',
            ],
            'package' => [
                'id' => $order->package->id ?? null,
                'name' => $order->package->name ?? '',
            ],
            'total_price' => (float) $order->total_price,
            'currency' => 'USD',
            'payment_method' => $order->payment_method === 'card' ? 'card' : 'cash',
            'payment_status' => $order->payment_status,
            'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
            'paid_at' => $order->updated_at->toIso8601String(),
            'created_at' => $order->created_at->toIso8601String(),
        ], 'Payment retrieved successfully', 200);
    }

    // ==========================================
    // SECTION 2: COMPANY MANAGER APIs
    // ==========================================

    public function companyDashboard(Company $company): JsonResponse
    {
        $services = Service::where('company_id', $company->id);
        $serviceIds = $services->pluck('id');

        $orders = Order::whereHas('package', fn($q) => $q->whereIn('service_id', $serviceIds));
        $paidOrders = (clone $orders)->whereIn('payment_status', ['captured', 'held']);

        return $this->successResponse([
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                ],
                'workers' => [
                    'total' => User::whereHas('workerProfile', fn($q) => $q->where('company_id', $company->id))->count(),
                ],
                'services' => [
                    'total' => $services->count(),
                ],
                'orders' => [
                    'total' => $orders->count(),
                    'pending' => (clone $orders)->where('status', 'pending')->count(),
                    'assigned_to_worker' => (clone $orders)->where('status', 'assigned_to_worker')->count(),
                    'on_way' => (clone $orders)->where('status', 'on_way')->count(),
                    'in_process' => (clone $orders)->where('status', 'in_process')->count(),
                    'completed' => (clone $orders)->where('status', 'completed')->count(),
                    'canceled' => (clone $orders)->where('status', 'canceled')->count(),
                ],
                'payments' => [
                    'gross_revenue' => (float) $paidOrders->sum('total_price'),
                    'company_profit' => (float) $paidOrders->sum('company_share'),
                    'system_profit' => (float) $paidOrders->sum('admin_share'),
                    'paid_payments_count' => $paidOrders->count(),
                ],
                'recent_orders' => (clone $orders)->latest()->take(5)->get(),
                'recent_payments' => (clone $paidOrders)->latest()->take(5)->get(),
            ], 'Company dashboard retrieved successfully', 200);
    }


    public function companyPaymentsSummary(Request $request, Company $company): JsonResponse
    {
        $query = Order::whereHas('package.service', fn($q) => $q->where('company_id', $company->id))
            ->whereIn('payment_status', ['captured', 'held']);

        if ($request->filled('service_id')) {
            $query->whereHas('package', fn($q) => $q->where('service_id', $request->service_id));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $grossRevenue = (float) $query->sum('total_price');
        $totalPayments = $query->count();

        $cashQuery = (clone $query)->where('payment_method', 'cash');
        $electricQuery = (clone $query)->where('payment_method', 'card');

        return $this->successResponse([
            'gross_revenue' => $grossRevenue,
            'total_payments' => $totalPayments,
            'average_payment' => $totalPayments > 0 ? round($grossRevenue / $totalPayments, 2) : 0,
            'cash' => [
                'total_amount' => (float) $cashQuery->sum('total_price'),
                'payments_count' => $cashQuery->count(),
            ],
            'electric' => [
                'total_amount' => (float) $electricQuery->sum('total_price'),
                'payments_count' => $electricQuery->count(),
            ],
            'system_profit' => (float) $query->sum('admin_share'),
            'company_profit' => (float) $query->sum('company_share'),
        ], 'Company payments summary retrieved successfully', 200);
    }


    public function companyPaymentsSearch(Request $request, Company $company): JsonResponse
    {
        $query = Order::with(['client', 'package.service'])
            ->whereHas('package.service', fn($q) => $q->where('company_id', $company->id));

        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('service_id')) {
            $query->whereHas('package', fn($q) => $q->where('service_id', $request->service_id));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $perPage = $request->get('per_page', 10);
        $orders = $query->latest()->paginate($perPage);

        return $this->successResponse($orders, 'Company payments search retrieved successfully', 200);
    }


    public function companyShowPayment(Company $company, $paymentId): JsonResponse
    {
        $order = Order::with(['client', 'package.service'])
            ->whereHas('package.service', fn($q) => $q->where('company_id', $company->id))
            ->find($paymentId);

        if (!$order) {
            return $this->errorResponse('Payment not found for this company', 404);
        }

        return $this->successResponse([
            'id' => $order->id,
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
            ],
            'client' => [
                'id' => $order->client_id,
                'name' => $order->client->name ?? '',
                'email' => $order->client->email ?? '',
            ],
            'service' => [
                'id' => $order->package->service->id ?? null,
                'name' => $order->package->service->name ?? '',
            ],
            'package' => [
                'id' => $order->package->id ?? null,
                'name' => $order->package->name ?? '',
            ],
            'total_price' => (float) $order->total_price,
            'currency' => 'USD',
            'payment_method' => $order->payment_method === 'card' ? 'card' : 'cash',
            'payment_status' => $order->payment_status,
            'system_share' => (float) $order->admin_share,
            'company_share' => (float) $order->company_share,
            'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
        ], 'Company payment retrieved successfully', 200);
    }

    public function companyServicesStats(Request $request, Company $company): JsonResponse
    {
        $services = Service::where('company_id', $company->id)->get()->map(function ($service) use ($request) {
            $orders = Order::whereHas('package', fn($q) => $q->where('service_id', $service->id));

            if ($request->filled('payment_method')) {
                $orders->where('payment_method', $request->payment_method);
            }

            if ($request->filled('from_date')) {
                $orders->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $orders->whereDate('created_at', '<=', $request->to_date);
            }

            $paidOrders = (clone $orders)->whereIn('payment_status', ['captured', 'held']);

            return [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'orders_count' => $orders->count(),
                'payments_count' => $paidOrders->count(),
                'gross_revenue' => (float) $paidOrders->sum('total_price'),
                'system_profit' => (float) $paidOrders->sum('admin_share'),
                'company_profit' => (float) $paidOrders->sum('company_share'),
                'cash_revenue' => (float) (clone $paidOrders)->where('payment_method', 'cash')->sum('total_price'),
                'electric_revenue' => (float) (clone $paidOrders)->where('payment_method', 'card')->sum('total_price'),
            ];
        });

        return $this->successResponse($services, 'Company services statistics retrieved successfully', 200);
    }


    public function companyRevenueChart(Request $request, Company $company): JsonResponse
    {
        $groupByParam = $request->get('group_by', $request->get('period', 'month'));
        $year = $request->get('year', date('Y'));

        $query = Order::whereHas('package.service', fn($q) => $q->where('company_id', $company->id))
            ->whereIn('payment_status', ['captured', 'held']);

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        } else {
            $query->whereYear('created_at', $year);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if (in_array($groupByParam, ['day', 'daily'])) {
            $groupBy = DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as period");
        } elseif (in_array($groupByParam, ['week', 'weekly'])) {
            $groupBy = DB::raw("CONCAT(YEAR(created_at), '-W', WEEK(created_at)) as period");
        } elseif (in_array($groupByParam, ['year', 'yearly'])) {
            $groupBy = DB::raw("YEAR(created_at) as period");
        } else {
            $groupBy = DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period");
        }

        $chartData = $query->select(
            $groupBy,
            DB::raw('SUM(total_price) as gross_revenue'),
            DB::raw('SUM(admin_share) as system_profit'),
            DB::raw('SUM(company_share) as company_profit')
        )
        ->groupBy('period')
        ->orderBy('period', 'ASC')
        ->get();

        return $this->successResponse($chartData, 'Company revenue chart retrieved successfully', 200);
    }

    // ==========================================
    // SECTION 3: ADMIN APIs
    // ==========================================


    public function adminDashboard(): JsonResponse
    {
        $paidOrders = Order::whereIn('payment_status', ['captured', 'held']);

        return $this->successResponse([
                'clients' => ['total' => User::where('role', 'client')->count()],
                'workers' => ['total' => User::whereHas('workerProfile')->count()],
                'companies' => [
                    'total' => Company::count(),
                    'blocked' => 0,
                ],
                'services' => ['total' => Service::count()],
                'orders' => [
                    'total' => Order::count(),
                    'pending' => Order::where('status', 'pending')->count(),
                    'assigned_to_worker' => Order::where('status', 'assigned_to_worker')->count(),
                    'on_way' => Order::where('status', 'on_way')->count(),
                    'in_process' => Order::where('status', 'in_process')->count(),
                    'completed' => Order::where('status', 'completed')->count(),
                    'canceled' => Order::where('status', 'canceled')->count(),
                ],
                'payments' => [
                    'gross_revenue' => (float) $paidOrders->sum('total_price'),
                    'system_profit' => (float) $paidOrders->sum('admin_share'),
                    'companies_profit' => (float) $paidOrders->sum('company_share'),
                    'paid_payments_count' => $paidOrders->count(),
                ],
                'recent_orders' => Order::latest()->take(5)->get(),
                'recent_payments' => $paidOrders->latest()->take(5)->get(),
            ], 'Admin dashboard retrieved successfully', 200);
    }


    public function adminPaymentsAnalytics(Request $request): JsonResponse
    {
        $query = Order::whereIn('payment_status', ['captured', 'held']);

        if ($request->filled('company_id')) {
            $query->whereHas('package.service', fn($q) => $q->where('company_id', $request->company_id));
        }

        if ($request->filled('region_id')) {
            $query->whereHas('package.service.company', fn($q) => $q->where('region_id', $request->region_id));
        }

        if ($request->filled('service_id')) {
            $query->whereHas('package', fn($q) => $q->where('service_id', $request->service_id));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $grossRevenue = (float) $query->sum('total_price');
        $totalPayments = $query->count();

        $cashQuery = (clone $query)->where('payment_method', 'cash');
        $electricQuery = (clone $query)->where('payment_method', 'card');

        return $this->successResponse([
            'gross_revenue' => $grossRevenue,
            'total_payments' => $totalPayments,
            'average_payment' => $totalPayments > 0 ? round($grossRevenue / $totalPayments, 2) : 0,
            'cash' => [
                'total_amount' => (float) $cashQuery->sum('total_price'),
                'payments_count' => $cashQuery->count(),
            ],
            'electric' => [
                'total_amount' => (float) $electricQuery->sum('total_price'),
                'payments_count' => $electricQuery->count(),
            ],
            'system_profit' => (float) $query->sum('admin_share'),
            'companies_profit' => (float) $query->sum('company_share'),
        ], 'Admin payments analytics retrieved successfully', 200);
    }


    public function adminRevenueChart(Request $request): JsonResponse
    {
        $groupByParam = $request->get('group_by', 'month');
        $query = Order::whereIn('payment_status', ['captured', 'held']);

        if ($request->filled('company_id')) {
            $query->whereHas('package.service', fn($q) => $q->where('company_id', $request->company_id));
        }

        if ($request->filled('region_id')) {
            $query->whereHas('package.service.company', fn($q) => $q->where('region_id', $request->region_id));
        }

        if ($request->filled('service_id')) {
            $query->whereHas('package', fn($q) => $q->where('service_id', $request->service_id));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if (in_array($groupByParam, ['day', 'daily'])) {
            $groupBy = DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as period");
        } elseif (in_array($groupByParam, ['week', 'weekly'])) {
            $groupBy = DB::raw("CONCAT(YEAR(created_at), '-W', WEEK(created_at)) as period");
        } elseif (in_array($groupByParam, ['year', 'yearly'])) {
            $groupBy = DB::raw("YEAR(created_at) as period");
        } else {
            $groupBy = DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period");
        }

        $chartData = $query->select(
            $groupBy,
            DB::raw('SUM(total_price) as gross_revenue'),
            DB::raw('SUM(admin_share) as system_profit'),
            DB::raw('SUM(company_share) as companies_profit')
        )
        ->groupBy('period')
        ->orderBy('period', 'ASC')
        ->get();

        return $this->successResponse($chartData, 'Admin revenue chart retrieved successfully', 200);
    }


    public function adminPaymentsSearch(Request $request): JsonResponse
    {
        $query = Order::with(['client', 'package.service.company.region']);

        if ($request->filled('company_id')) {
            $query->whereHas('package.service', fn($q) => $q->where('company_id', $request->company_id));
        }

        if ($request->filled('region_id')) {
            $query->whereHas('package.service.company', fn($q) => $q->where('region_id', $request->region_id));
        }

        if ($request->filled('service_id')) {
            $query->whereHas('package', fn($q) => $q->where('service_id', $request->service_id));
        }

        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $perPage = $request->get('per_page', 10);
        $orders = $query->latest()->paginate($perPage);

        return $this->successResponse($orders, 'Admin payments search retrieved successfully', 200);
    }


    public function adminCompaniesStats(Request $request): JsonResponse
{
    $companiesQuery = Company::query();

    if ($request->filled('region_id')) {
        $companiesQuery->where('region_id', $request->region_id);
    }

    if ($request->filled('service_id')) {
        $companiesQuery->whereHas('services', function($q) use ($request) {
            $q->where('id', $request->service_id);
        });
    }

    $companies = $companiesQuery->get();

    $result = $companies->map(function ($company) use ($request) {
        $ordersQuery = Order::whereHas('package.service', function($q) use ($company) {
            $q->where('company_id', $company->id);
        });

        if ($request->filled('payment_method')) {
            $ordersQuery->where('payment_method', $request->payment_method);
        }

        if ($request->filled('from_date')) {
            $ordersQuery->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $ordersQuery->whereDate('created_at', '<=', $request->to_date);
        }

        $paidOrders = (clone $ordersQuery)->whereIn('payment_status', ['captured', 'held']);

        return [
            'company_id' => $company->id,
            'company_name' => $company->name_en,
            'region_id' => $company->region_id,
            'orders_count' => $ordersQuery->count(),
            'payments_count' => $paidOrders->count(),
            'gross_revenue' => (float) $paidOrders->sum('total_price'),
            'cash_revenue' => (float) (clone $paidOrders)->where('payment_method', 'cash')->sum('total_price'),
            'electric_revenue' => (float) (clone $paidOrders)->where('payment_method', 'card')->sum('total_price'),
            'system_profit' => (float) $paidOrders->sum('admin_share'),
            'company_profit' => (float) $paidOrders->sum('company_share'),
        ];
    });

    return $this->successResponse($result, 'Admin companies statistics retrieved successfully', 200);
}



    public function adminRegionsStats(Request $request): JsonResponse
    {
        $regions = Region::all()->map(function ($region) use ($request) {
            $orders = Order::whereHas('package.service.company', fn($q) => $q->where('region_id', $region->id));

            if ($request->filled('payment_method')) {
                $orders->where('payment_method', $request->payment_method);
            }

            if ($request->filled('from_date')) {
                $orders->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $orders->whereDate('created_at', '<=', $request->to_date);
            }

            $paidOrders = (clone $orders)->whereIn('payment_status', ['captured', 'held']);

            return [
                'region_id' => $region->id,
                'region_name' => $region->name,
                'companies_count' => Company::where('region_id', $region->id)->count(),
                'orders_count' => $orders->count(),
                'payments_count' => $paidOrders->count(),
                'gross_revenue' => (float) $paidOrders->sum('total_price'),
                'cash_revenue' => (float) (clone $paidOrders)->where('payment_method', 'cash')->sum('total_price'),
                'electric_revenue' => (float) (clone $paidOrders)->where('payment_method', 'card')->sum('total_price'),
                'system_profit' => (float) $paidOrders->sum('admin_share'),
                'companies_profit' => (float) $paidOrders->sum('company_share'),
            ];
        });

        return $this->successResponse($regions, 'Admin regions statistics retrieved successfully', 200);
    }


    public function adminServicesStats(Request $request): JsonResponse
    {
        $services = Service::all()->map(function ($service) use ($request) {
            $orders = Order::whereHas('package', fn($q) => $q->where('service_id', $service->id));

            if ($request->filled('company_id')) {
                $orders->whereHas('package.service', fn($q) => $q->where('company_id', $request->company_id));
            }

            if ($request->filled('region_id')) {
                $orders->whereHas('package.service.company', fn($q) => $q->where('region_id', $request->region_id));
            }

            if ($request->filled('payment_method')) {
                $orders->where('payment_method', $request->payment_method);
            }

            if ($request->filled('from_date')) {
                $orders->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $orders->whereDate('created_at', '<=', $request->to_date);
            }

            $paidOrders = (clone $orders)->whereIn('payment_status', ['captured', 'held']);

            return [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'orders_count' => $orders->count(),
                'payments_count' => $paidOrders->count(),
                'gross_revenue' => (float) $paidOrders->sum('total_price'),
                'cash_revenue' => (float) (clone $paidOrders)->where('payment_method', 'cash')->sum('total_price'),
                'electric_revenue' => (float) (clone $paidOrders)->where('payment_method', 'card')->sum('total_price'),
                'system_profit' => (float) $paidOrders->sum('admin_share'),
                'companies_profit' => (float) $paidOrders->sum('company_share'),
            ];
        });

        return $this->successResponse($services, 'Admin services statistics retrieved successfully', 200);
    }


    public function adminShowPayment($id): JsonResponse
    {
        $order = Order::with(['client', 'package.service.company.region'])->find($id);

        if (!$order) {
            return $this->errorResponse('Payment record not found', 404);
        }

        $service = $order->package->service ?? null;
        $company = $service->company ?? null;

        return $this->successResponse([
            'id' => $order->id,
            'order' => ['id' => $order->id],
            'client' => [
                'id' => $order->client_id,
                'name' => $order->client->name ?? 'Client'
            ],
            'company' => [
                'id' => $company->id ?? null,
                'name' => $company->name ?? ''
            ],
            'region' => [
                'id' => $company->region->id ?? null,
                'name' => $company->region->name ?? ''
            ],
            'service' => [
                'id' => $service->id ?? null,
                'name' => $service->name ?? ''
            ],
            'amount' => (float) $order->total_price,
            'payment_method' => $order->payment_method === 'card' ? 'card' : 'cash',
            'payment_status' => $order->payment_status,
            'system_profit' => (float) $order->admin_share,
            'company_profit' => (float) $order->company_share,
            'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
            'paid_at' => $order->updated_at->toIso8601String(),
        ], 'Admin payment retrieved successfully', 200);
    }
}
