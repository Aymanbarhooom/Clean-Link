<?php

namespace App\Services\Chat;

use App\Models\{Category, ChatConversation, Company, Favorite, Order, Package, Region, Review, Service, User};
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class CleanLinkChatToolService
{
    public function __construct(private readonly DistanceService $distanceService, private readonly ChatBookingService $bookings) {}

    public function execute(string $name, array $args, User $user, ?ChatConversation $conversation = null, string $message = ''): array
    {
        try {
            if (!$conversation && in_array($name, [
                'get_available_dates', 'get_available_slots', 'get_booking_draft',
                'update_booking_draft', 'validate_booking_draft', 'get_booking_summary',
                'create_order_from_booking_draft',
            ], true)) {
                return ['error' => 'A persisted chat conversation is required for booking tools.'];
            }

            return match ($name) {
                'search_categories' => $this->categories($args),
                'get_category_details' => $this->category((int) ($args['category_id'] ?? 0)),
                'search_regions' => $this->regions($args),
                'get_region_details' => $this->region((int) ($args['region_id'] ?? 0)),
                'search_companies' => $this->companies($args),
                'get_company_details' => $this->company((int) ($args['company_id'] ?? 0)),
                'compare_companies' => $this->compare($args, $user),
                'find_nearby_companies' => $this->nearby($args, $user),
                'search_services' => $this->services($args),
                'get_service_details' => $this->service((int) ($args['service_id'] ?? 0)),
                'get_company_services' => $this->companyServices((int) ($args['company_id'] ?? 0)),
                'get_service_packages' => $this->servicePackages((int) ($args['service_id'] ?? 0)),
                'get_package_details' => $this->package((int) ($args['package_id'] ?? 0)),
                'get_open_package_requirements' => $this->bookings->requirements((int) ($args['package_id'] ?? 0)),
                'get_my_locations' => $this->locations($user),
                'get_location_details' => $this->location((int) ($args['location_id'] ?? 0), $user),
                'get_available_dates', 'get_available_slots' => $this->bookings->availability($conversation, $user, $args),
                'get_my_orders' => $this->orders($args, $user),
                'get_my_last_order' => $this->lastOrder($user),
                'get_my_order' => $this->order((int) ($args['order_id'] ?? 0), $user),
                'get_payment_methods' => $this->paymentMethods(),
                'get_reviews', 'get_company_reviews' => $this->reviews($args),
                'get_current_offers' => $this->offers(),
                'get_my_favorites' => $this->favorites($user),
                'get_booking_draft' => ['draft' => $this->bookings->get($conversation, $user)],
                'update_booking_draft' => $this->bookings->update($conversation, $user, $args),
                'validate_booking_draft', 'get_booking_summary' => $this->bookings->summary($conversation, $user),
                'create_order_from_booking_draft' => $this->bookings->create($conversation, $user, $message),
                default => ['error' => 'Unknown CleanLink tool.'],
            };
        } catch (Throwable $e) {
            report($e);
            return ['error' => $e instanceof HttpExceptionInterface && $e->getStatusCode() < 500
                ? ($e->getMessage() ?: 'The selected value is invalid.') : 'CleanLink could not complete this operation safely.'];
        }
    }

    private function categories(array $args): array
    {
        $q = Category::withCount('services'); $this->search($q, $args['query'] ?? '');
        return ['categories' => $q->limit(20)->get()->map(fn ($x) => ['id' => $x->id, ...$this->name($x),
            'description_ar' => $x->description_ar, 'description_en' => $x->description_en, 'services_count' => $x->services_count])->all()];
    }

    private function category(int $id): array
    {
        $x = Category::with('services.company')->find($id);
        return $x ? ['found' => true, 'category' => ['id' => $x->id, ...$this->name($x), 'description_ar' => $x->description_ar,
            'description_en' => $x->description_en, 'services' => $x->services->map(fn ($s) => $this->serviceSummary($s))->all()]] : ['found' => false];
    }

    private function regions(array $args): array
    {
        $q = Region::withCount('companies'); $this->search($q, $args['query'] ?? '');
        return ['regions' => $q->limit(20)->get()->map(fn ($x) => ['id' => $x->id, ...$this->name($x), 'companies_count' => $x->companies_count])->all()];
    }

    private function region(int $id): array
    {
        $x = Region::with('companies')->find($id);
        return $x ? ['found' => true, 'region' => ['id' => $x->id, ...$this->name($x),
            'companies' => $x->companies->map(fn ($c) => $this->companySummary($c))->all()]] : ['found' => false];
    }

    private function companies(array $args): array
    {
        $q = Company::with('services'); $this->search($q, $args['query'] ?? '');
        if ($term = trim((string) ($args['service_query'] ?? ''))) $q->whereHas('services', fn ($b) => $this->search($b, $term));
        $items = $q->get()->map(fn ($c) => [...$this->companySummary($c),
            'services' => $c->services->map(fn ($s) => $this->serviceSummary($s))->all()]);
        return ['companies' => $items->all(), '_action' => ['type' => 'select_company', 'title' => 'Choose a company',
            'options' => $items->map(fn ($c) => ['label' => $c['name_en'] ?? $c['name_ar'], 'label_ar' => $c['name_ar'] ?? $c['name_en'],
                'value' => $c['id'], 'message' => 'Use company '.($c['name_en'] ?? $c['name_ar']),
                'message_ar' => 'استخدم شركة '.($c['name_ar'] ?? $c['name_en'])])->all()]];
    }

    private function company(int $id): array
    {
        $x = Company::with(['region', 'services'])->find($id);
        return $x ? ['found' => true, 'company' => [...$this->companySummary($x), 'description_ar' => $x->description_ar,
            'description_en' => $x->description_en, 'location_ar' => $x->location_ar, 'location_en' => $x->location_en,
            'region' => $x->region ? ['id' => $x->region->id, ...$this->name($x->region)] : null,
            'services' => $x->services->map(fn ($s) => $this->serviceSummary($s))->all()]] : ['found' => false];
    }

    private function compare(array $args, User $user): array
    {
        $ids = collect($args['company_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->take(10);
        $location = isset($args['location_id']) ? $user->locations()->whereKey((int) $args['location_id'])->first() : null;
        if (isset($args['location_id']) && !$location) return ['error' => 'Location does not belong to the authenticated client.'];
        return ['companies' => Company::with('services')->whereIn('id', $ids)->get()->map(fn ($c) => [...$this->companySummary($c),
            'distance_km' => $this->distance($c, $location), 'minimum_service_price' => $c->services->min('minimum_price')])->all()];
    }

    private function nearby(array $args, User $user): array
    {
        $location = $user->locations()->whereKey((int) ($args['location_id'] ?? 0))->first();
        if (!$location) return ['error' => 'Location does not belong to the authenticated client.'];
        $items = Company::with('services')->get()->map(fn ($c) => [...$this->companySummary($c), 'distance_km' => $this->distance($c, $location)])
            ->sortBy(fn ($x) => $x['distance_km'] ?? PHP_FLOAT_MAX)->take(10)->values()->all();
        return ['location' => ['id' => $location->id, 'name' => $location->name], 'companies' => $items];
    }

    private function services(array $args): array
    {
        $q = Service::with('company'); $this->search($q, $args['query'] ?? '');
        return ['services' => $q->limit(20)->get()->map(fn ($s) => $this->serviceSummary($s))->all()];
    }

    private function service(int $id): array
    {
        $x = Service::with(['company', 'category', 'packages'])->find($id);
        return $x ? ['found' => true, 'service' => [...$this->serviceSummary($x), 'description_ar' => $x->description_ar,
            'description_en' => $x->description_en, 'category' => $x->category ? ['id' => $x->category->id, ...$this->name($x->category)] : null,
            'packages' => $x->packages->map(fn ($p) => $this->packageSummary($p))->all()]] : ['found' => false];
    }

    private function companyServices(int $id): array
    {
        $x = Company::with('services')->find($id);
        if (!$x) return ['found' => false];
        $items = $x->services->map(fn ($s) => $this->serviceSummary($s));
        return ['found' => true, 'company' => $this->companySummary($x), 'services' => $items->all(),
            '_action' => ['type' => 'select_service', 'title' => 'Choose a service', 'options' => $items->map(fn ($s) => [
                'label' => $s['name_en'] ?? $s['name_ar'], 'label_ar' => $s['name_ar'] ?? $s['name_en'], 'value' => $s['id'],
                'message' => 'Use service '.($s['name_en'] ?? $s['name_ar']),
                'message_ar' => 'استخدم خدمة '.($s['name_ar'] ?? $s['name_en'])])->all()]];
    }

    private function servicePackages(int $id): array
    {
        $x = Service::with(['company', 'packages'])->find($id);
        if (!$x) return ['found' => false];
        $items = $x->packages->map(fn ($p) => $this->packageSummary($p));
        return ['found' => true, 'service' => $this->serviceSummary($x), 'packages' => $items->all(),
            '_action' => ['type' => 'select_package', 'title' => 'Choose a package', 'options' => $items->map(fn ($p) => [
                'label' => $p['name_en'] ?? $p['name_ar'], 'label_ar' => $p['name_ar'] ?? $p['name_en'], 'value' => $p['id'],
                'message' => 'Use package '.($p['name_en'] ?? $p['name_ar']),
                'message_ar' => 'استخدم باقة '.($p['name_ar'] ?? $p['name_en'])])->all()]];
    }

    private function package(int $id): array
    {
        $x = Package::with('service.company')->find($id);
        return $x ? ['found' => true, 'package' => [...$this->packageSummary($x), 'details_ar' => $x->details_ar,
            'details_en' => $x->details_en, 'service' => $this->serviceSummary($x->service), 'company' => $this->companySummary($x->service->company)]] : ['found' => false];
    }

    private function locations(User $user): array
    {
        $items = $user->locations()->get()->map(fn ($x) => ['id' => $x->id, 'name' => $x->name, 'address' => $x->address,
            'latitude' => $x->latitude, 'longitude' => $x->longitude])->all();
        if (count($items) === 1) {
            return [
                'locations' => $items,
                'auto_selected_location' => $items[0],
            ];
        }
        return ['locations' => $items, '_action' => ['type' => 'select_location', 'title' => 'Choose location',
            'options' => collect($items)->map(fn ($x) => ['label' => $x['name'], 'label_ar' => $x['name'], 'value' => $x['id'],
                'message' => 'Use '.$x['name'].' location', 'message_ar' => 'استخدم موقع '.$x['name']])->all()]];
    }

    private function location(int $id, User $user): array
    {
        $x = $user->locations()->whereKey($id)->first();
        return $x ? ['found' => true, 'location' => ['id' => $x->id, 'name' => $x->name, 'address' => $x->address,
            'latitude' => $x->latitude, 'longitude' => $x->longitude]] : ['found' => false];
    }

    private function orders(array $args, User $user): array
    {
        $q = Order::with('package.service.company')->where('client_id', $user->id)->latest();
        if ($status = trim((string) ($args['status'] ?? ''))) $q->where('status', $status);
        return ['orders' => $q->limit(20)->get()->map(fn ($o) => $this->orderSummary($o))->all()];
    }

    private function lastOrder(User $user): array
    {
        $x = Order::with('package.service.company')->where('client_id', $user->id)->latest()->first();
        return $x ? ['found' => true, 'order' => $this->orderSummary($x)] : ['found' => false];
    }

    private function order(int $id, User $user): array
    {
        $x = Order::with(['package.service.company', 'attributes'])->where('client_id', $user->id)->find($id);
        return $x ? ['found' => true, 'order' => $this->orderSummary($x)] : ['found' => false];
    }

    private function paymentMethods(): array
    {
        $items = [['value' => 'cash', 'label' => 'Cash', 'label_ar' => 'نقداً', 'message' => 'Pay with cash', 'message_ar' => 'الدفع نقداً'],
            ['value' => 'card', 'label' => 'Card', 'label_ar' => 'بطاقة', 'message' => 'Pay with card', 'message_ar' => 'الدفع بالبطاقة']];
        return ['payment_methods' => $items, 'card_note' => 'Card orders are cancelled if payment is not confirmed within 10 minutes.',
            '_action' => ['type' => 'select_payment_method', 'title' => 'Payment method',
                'electronic_payment_warning' => true, 'options' => $items]];
    }

    private function reviews(array $args): array
    {
        $type = ($args['type'] ?? 'company') === 'service' ? Service::class : Company::class;
        $id = (int) ($args['id'] ?? $args['company_id'] ?? 0);
        return ['reviews' => Review::where('reviewable_type', $type)->where('reviewable_id', $id)->latest()->limit(20)->get()
            ->map(fn ($x) => ['rating' => $x->rating, 'comment' => $x->comment, 'created_at' => $x->created_at?->toIso8601String()])->all()];
    }

    private function offers(): array
    {
        return ['offers' => Service::with('company')->where('discount', '>', 0)->orderByDesc('discount')->limit(20)->get()
            ->map(fn ($s) => [...$this->serviceSummary($s), 'discount' => (float) $s->discount])->all()];
    }

    private function favorites(User $user): array
    {
        return ['favorites' => Favorite::with('favoritable')->where('user_id', $user->id)->latest()->limit(30)->get()->map(fn ($x) => [
            'type' => class_basename($x->favoritable_type), 'id' => $x->favoritable_id,
            'name_ar' => $x->favoritable?->name_ar, 'name_en' => $x->favoritable?->name_en])->all()];
    }

    private function companySummary(Company $x): array { return ['id' => $x->id, ...$this->name($x), 'rating' => $x->rating, 'latitude' => $x->latitude, 'longitude' => $x->longitude]; }
    private function serviceSummary(Service $x): array { return ['id' => $x->id, ...$this->name($x), 'company_id' => $x->company_id,
        'company' => $x->relationLoaded('company') && $x->company ? ['id' => $x->company->id, ...$this->name($x->company)] : null,
        'rating' => $x->rating, 'minimum_price' => $x->minimum_price, 'maximum_price' => $x->maximum_price,
        'min_duration' => $x->min_duration, 'max_duration' => $x->max_duration, 'discount' => $x->discount]; }
    private function packageSummary(Package $x): array { return ['id' => $x->id, ...$this->name($x), 'service_id' => $x->service_id,
        'duration' => $x->duration, 'price' => $x->price, 'price_after_discount' => $x->price_after_discount,
        'minimum_workers' => $x->minimum_workers, 'is_open_package' => $x->is_open_package]; }
    private function orderSummary(Order $x): array { return ['id' => $x->id, 'status' => $x->status, 'payment_method' => $x->payment_method,
        'payment_status' => $x->payment_status, 'total_price' => $x->total_price, 'start_time' => $x->start_time?->toIso8601String(),
        'location' => $x->location, 'package' => $x->package ? $this->packageSummary($x->package) : null,
        'service' => $x->package?->service ? $this->serviceSummary($x->package->service) : null,
        'company' => $x->package?->service?->company ? $this->companySummary($x->package->service->company) : null]; }
    private function name(object $x): array { return ['name_ar' => $x->name_ar, 'name_en' => $x->name_en]; }
    private function search(Builder $q, string $text): void { if ($text = trim($text)) $q->where(fn ($b) => $b->where('name_en', 'like', "%$text%")->orWhere('name_ar', 'like', "%$text%")); }
    private function distance(Company $company, ?object $location): ?float
    {
        return $location && $location->latitude !== null && $location->longitude !== null && $company->latitude !== null && $company->longitude !== null
            ? $this->distanceService->calculateKm((float) $location->latitude, (float) $location->longitude, (float) $company->latitude, (float) $company->longitude) : null;
    }
}
