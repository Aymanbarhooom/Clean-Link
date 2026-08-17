<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Order;
use App\Models\Region;
use App\Models\Service;
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

        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        if ($order->client_id !== auth()->id()) {
            return $this->errorResponse(
                'You are not authorized to pay for this order',
                403
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Method
        |--------------------------------------------------------------------------
        */

        if ($order->payment_method !== 'electric') {
            return $this->errorResponse(
                'This order does not use electronic payment',
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Status
        |--------------------------------------------------------------------------
        */

        if ($order->payment_status !== 'pending') {
            return $this->errorResponse(
                'This order is not available for payment',
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Order Status
        |--------------------------------------------------------------------------
        */

        if (in_array($order->status, ['canceled', 'completed', 'in_process'])) {
            return $this->errorResponse(
                'This order cannot be paid',
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Stripe
        |--------------------------------------------------------------------------
        */

        try {
            $stripe = new StripeClient(
                config('services.stripe.secret')
            );

            /*
            |--------------------------------------------------------------------------
            | Convert price to smallest currency unit
            |--------------------------------------------------------------------------
            */

            $amount = (int) round($order->total_price * 100);

            if ($amount <= 0) {
                return $this->errorResponse(
                    'Invalid order amount',
                    422
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create PaymentIntent
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Save Stripe PaymentIntent ID
            |--------------------------------------------------------------------------
            */

            $order->update([
                'stripe_payment_intent_id' => $paymentIntent->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

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

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        // بداية الاستعلام بناءً على التسلسل: Region -> Company -> Service -> Package -> Order
        $query = Order::with(['client', 'package.service.company.region', 'latestPayment']);

        // 1. تحديد النطاق حسب الصلاحيات
        if ($user->role === 'client') {
            $query->where('client_id', $user->id);
        } elseif ($user->role === 'companyManager') {
            $companyIds = Company::where('manager_id', $user->id)->pluck('id');
            $query->whereHas('package.service.company', function ($q) use ($companyIds) {
                $q->whereIn('id', $companyIds);
            });
        }
        // إذا كان admin يجلب الكل مباشرة

        // 2. تطبيق الفلاتر الاختيارية
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

        $orders = $query->latest()->paginate(15);

        return $this->successResponse(
            $orders,
            'Orders retrieved successfully',
            200
        );
    }

    /**
     * 2. GET /api/payments/{id}
     * عرض تفاصيل دفعة/طلب محدد
     */
    public function show($id): JsonResponse
    {
        $order = Order::with(['client', 'package.service.company.region', 'latestPayment'])->find($id);

        if (!$order) {
            return $this->errorResponse('Payment record not found', 404);
        }

        return $this->successResponse(
            $order,
            'Payment record retrieved successfully',
            200
        );
    }

    /**
     * 3. إحصائيات مدير الشركة العامة + نسختي (service_id / region_id)
     */
    public function companyAnalytics(Request $request): JsonResponse
    {
        $query = Order::query()->whereIn('payment_status', ['paid', 'held']);

        // فلترة حسب الشركة أو الخدمة أو المنطقة
        if ($request->filled('company_id')) {
            $query->whereHas('package.service', fn($q) => $q->where('company_id', $request->company_id));
        }

        if ($request->filled('service_id')) {
            $query->whereHas('package', fn($q) => $q->where('service_id', $request->service_id));
        }

        if ($request->filled('region_id')) {
            $query->whereHas('package.service.company', fn($q) => $q->where('region_id', $request->region_id));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $totalRevenue = (float) $query->sum('total_price');
        $totalPayments = $query->count();

        $cashQuery = (clone $query)->where('payment_method', 'cash');
        $cashTotal = (float) $cashQuery->sum('total_price');
        $cashCount = $cashQuery->count();

        $stripeQuery = (clone $query)->where('payment_method', 'electric');
        $stripeTotal = (float) $stripeQuery->sum('total_price');
        $stripeCount = $stripeQuery->count();

        $systemProfit = (float) $query->sum('admin_share');
        $companyNetProfit = (float) $query->sum('company_share');

        $averagePayment = $totalPayments > 0 ? round($totalRevenue / $totalPayments, 2) : 0;

        return $this->successResponse(
            [
                'total_revenue' => $totalRevenue,
                'total_payments' => $totalPayments,
                'cash' => [
                    'total_amount' => $cashTotal,
                    'payments_count' => $cashCount,
                ],
                'stripe' => [
                    'total_amount' => $stripeTotal,
                    'payments_count' => $stripeCount,
                ],
                'system_profit' => $systemProfit,
                'company_net_profit' => $companyNetProfit,
                'average_payment' => $averagePayment,
            ],
            'Analytics retrieved successfully',
            200
        );
    }

    /**
     * 4. GET /api/companies/{company}/payments/revenue-chart
     * الرسم البياني المالي للشركة (daily, weekly, monthly, yearly)
     */
    public function companyRevenueChart(Request $request, Company $company): JsonResponse
    {
        $period = $request->get('period', 'monthly');
        $year = $request->get('year', date('Y'));

        $query = Order::whereHas('package.service', fn($q) => $q->where('company_id', $company->id))
            ->whereIn('payment_status', ['paid', 'held'])
            ->whereYear('created_at', $year);

        if ($period === 'daily') {
            $groupBy = DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as period");
        } elseif ($period === 'weekly') {
            $groupBy = DB::raw("CONCAT(YEAR(created_at), '-W', WEEK(created_at)) as period");
        } elseif ($period === 'yearly') {
            $groupBy = DB::raw("YEAR(created_at) as period");
        } else {
            // monthly كخيار افتراضي
            $groupBy = DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period");
        }

        $chartData = $query->select(
            $groupBy,
            DB::raw('SUM(total_price) as revenue'),
            DB::raw('SUM(admin_share) as system_profit'),
            DB::raw('SUM(company_share) as company_profit')
        )
        ->groupBy('period')
        ->orderBy('period', 'ASC')
        ->get();

        return $this->successResponse(
            $chartData,
            'Revenue chart data retrieved successfully',
            200
        );
    }

    /**
     * Admin 1: GET /api/admin/payments/companies-statistics
     */
    public function adminCompaniesStats(): JsonResponse
    {
        $companies = Company::all()->map(function ($company) {
            $orders = Order::whereHas('package.service', fn($q) => $q->where('company_id', $company->id));
            $paidOrders = (clone $orders)->whereIn('payment_status', ['paid', 'held']);

            return [
                'company_id' => $company->id,
                'company_name' => $company->name ?? 'Company #' . $company->id,
                'orders_count' => $orders->count(),
                'payments_count' => $paidOrders->count(),
                'gross_revenue' => (float) $paidOrders->sum('total_price'),
                'cash_revenue' => (float) (clone $paidOrders)->where('payment_method', 'cash')->sum('total_price'),
                'stripe_revenue' => (float) (clone $paidOrders)->where('payment_method', 'electric')->sum('total_price'),
                'system_profit' => (float) $paidOrders->sum('admin_share'),
                'company_profit' => (float) $paidOrders->sum('company_share'),
            ];
        });

        return $this->successResponse(
            $companies,
            'Companies statistics retrieved successfully',
            200
        );
    }

    /**
     * Admin 2: GET /api/admin/payments/regions-statistics
     */
    public function adminRegionsStats(): JsonResponse
    {
        $regions = Region::all()->map(function ($region) {
            $orders = Order::whereHas('package.service.company', fn($q) => $q->where('region_id', $region->id));
            $paidOrders = (clone $orders)->whereIn('payment_status', ['paid', 'held']);

            return [
                'region_id' => $region->id,
                'region_name' => $region->name ?? 'Region #' . $region->id,
                'companies_count' => Company::where('region_id', $region->id)->count(),
                'orders_count' => $orders->count(),
                'gross_revenue' => (float) $paidOrders->sum('total_price'),
                'system_profit' => (float) $paidOrders->sum('admin_share'),
                'companies_profit' => (float) $paidOrders->sum('company_share'),
            ];
        });

        return $this->successResponse(
            $regions,
            'Regions statistics retrieved successfully',
            200
        );
    }

    /**
     * Admin 3: GET /api/admin/payments/services-statistics
     */
    public function adminServicesStats(): JsonResponse
    {
        $services = Service::all()->map(function ($service) {
            $orders = Order::whereHas('package', fn($q) => $q->where('service_id', $service->id));
            $paidOrders = (clone $orders)->whereIn('payment_status', ['paid', 'held']);

            return [
                'service_id' => $service->id,
                'service_name' => $service->name ?? 'Service #' . $service->id,
                'orders_count' => $orders->count(),
                'gross_revenue' => (float) $paidOrders->sum('total_price'),
                'system_profit' => (float) $paidOrders->sum('admin_share'),
            ];
        });

        return $this->successResponse(
            $services,
            'Services statistics retrieved successfully',
            200
        );
    }

    /**
     * Admin 4: GET /api/admin/payments/{payment}
     */
    public function adminShowPayment($id): JsonResponse
    {
        $order = Order::with(['client', 'package.service.company.region'])->find($id);

        if (!$order) {
            return $this->errorResponse('Payment record not found', 404);
        }

        $service = $order->package->service ?? null;
        $company = $service->company ?? null;

        return $this->successResponse(
            [
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
                'payment_method' => $order->payment_method === 'electric' ? 'stripe' : 'cash',
                'payment_status' => $order->payment_status,
                'system_profit' => (float) $order->admin_share,
                'company_profit' => (float) $order->company_share,
                'stripe_payment_intent_id' => $order->stripe_payment_intent_id,
                'paid_at' => $order->updated_at->format('Y-m-d H:i:s'),
            ],
            'Payment record retrieved successfully',
            200
        );
    }

}