<?php

namespace App\Services\Chat;

use App\Http\Controllers\OrderController;
use App\Models\ChatBookingDraft;
use App\Models\ChatConversation;
use App\Models\Order;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use App\Services\GoogleRoutesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ChatBookingService
{
    public function __construct(
        private readonly OrderController $orders,
        private readonly GoogleRoutesService $routes,
    ) {}

    public function get(ChatConversation $conversation, User $user): array
    {
        return $this->draftData($this->ownedDraft($conversation, $user));
    }

    public function update(ChatConversation $conversation, User $user, array $input): array
    {
        $draft = $this->ownedDraft($conversation, $user);
        $changes = [];

        if (array_key_exists('company_id', $input)) {
            $companyId = (int) $input['company_id'];
            abort_unless(DB::table('companies')->where('id', $companyId)->exists(), 422, 'Company not found.');
            if ($draft->company_id !== $companyId) {
                $changes = array_merge($changes, ['company_id' => $companyId, 'service_id' => null, 'package_id' => null,
                    'start_time' => null, 'open_package_attributes' => null]);
            }
        }

        if (array_key_exists('service_id', $input)) {
            $service = Service::with('company')->findOrFail((int) $input['service_id']);
            $selectedCompanyId = $changes['company_id'] ?? $draft->company_id;
            abort_unless($selectedCompanyId, 422, 'Select a company before selecting a service.');
            if ($service->company_id !== $selectedCompanyId) {
                abort(422, 'Service does not belong to the selected company.');
            }
            if ($draft->service_id !== $service->id) {
                $changes = array_merge($changes, ['company_id' => $service->company_id, 'service_id' => $service->id,
                    'package_id' => null, 'start_time' => null, 'open_package_attributes' => null]);
            }
        }

        if (array_key_exists('package_id', $input)) {
            $package = Package::with('service')->findOrFail((int) $input['package_id']);
            $selectedServiceId = $changes['service_id'] ?? $draft->service_id;
            abort_unless($selectedServiceId, 422, 'Select a service before selecting a package.');
            if ($package->service_id !== $selectedServiceId) {
                abort(422, 'Package does not belong to the selected service.');
            }
            if ($draft->package_id !== $package->id) {
                $changes = array_merge($changes, ['company_id' => $package->service->company_id, 'service_id' => $package->service_id,
                    'package_id' => $package->id, 'start_time' => null, 'open_package_attributes' => null]);
            }
        }

        if (array_key_exists('location_id', $input)) {
            abort_unless($changes['package_id'] ?? $draft->package_id, 422, 'Select a package before selecting a location.');
            $location = $user->locations()->whereKey((int) $input['location_id'])->first();
            abort_unless($location, 422, 'Location does not belong to the authenticated client.');
            if ($draft->location_id !== $location->id) {
                $changes = array_merge($changes, ['location_id' => $location->id, 'start_time' => null]);
            }
        }

        if (array_key_exists('payment_method', $input)) {
            abort_unless($changes['start_time'] ?? $draft->start_time, 422, 'Select an available date and time before choosing payment.');
            $method = match (Str::lower(trim((string) $input['payment_method']))) {
                'cash', 'manual' => 'manual',
                'card', 'electronic', 'electric', 'stripe' => 'electric',
                default => abort(422, 'Supported payment methods are cash and card.'),
            };
            $changes['payment_method'] = $method;
        }

        if (array_key_exists('note', $input)) {
            abort_unless($changes['payment_method'] ?? $draft->payment_method, 422, 'Select a payment method before adding a note.');
            $note = trim((string) $input['note']);
            abort_if(mb_strlen($note) > 1000, 422, 'The note is too long.');
            $changes['note'] = $note === '' ? null : $note;
            $changes['note_handled'] = true;
        } elseif (($input['skip_note'] ?? false) === true) {
            abort_unless($changes['payment_method'] ?? $draft->payment_method, 422, 'Select a payment method before skipping the note.');
            $changes['note'] = null;
            $changes['note_handled'] = true;
        }

        if (array_key_exists('attributes', $input)) {
            abort_unless($draft->package_id || isset($changes['package_id']), 422, 'Select a package first.');
            $package = Package::with('service.attributes')->find($changes['package_id'] ?? $draft->package_id);
            abort_unless($package && $package->is_open_package, 422, 'Attributes apply only to an Open Package.');
            $allowed = $package->service->attributes->keyBy('id');
            $submitted = collect($input['attributes'])->map(function ($item) use ($allowed) {
                $id = (int) ($item['id'] ?? 0);
                $qty = (int) ($item['qty'] ?? 0);
                abort_unless($allowed->has($id) && $qty >= 1, 422, 'Invalid Open Package attribute.');
                return ['id' => $id, 'qty' => $qty];
            })->unique('id')->keyBy('id');
            $attributes = collect($draft->open_package_attributes ?? [])->keyBy('id');
            foreach ($submitted as $id => $item) $attributes->put($id, $item);
            $changes['open_package_attributes'] = $attributes->values()->all();
            $changes['start_time'] = null;
        }

        if (array_key_exists('booking_date', $input) || array_key_exists('slot', $input)) {
            $package = Package::with('service.attributes')->find($changes['package_id'] ?? $draft->package_id);
            abort_unless($package, 422, 'Select a package before choosing a date and time.');
            abort_unless($changes['location_id'] ?? $draft->location_id, 422, 'Select a saved location before choosing a date and time.');
            if ($package->is_open_package) {
                $this->assertAllOpenAttributes($package, $changes['open_package_attributes'] ?? $draft->open_package_attributes ?? []);
            }
            $date = (string) ($input['booking_date'] ?? optional($draft->start_time)->format('Y-m-d'));
            $time = (string) ($input['slot'] ?? optional($draft->start_time)->format('H:i'));
            abort_unless($date !== '' && $time !== '', 422, 'Both booking date and time are required.');
            try {
                $start = Carbon::createFromFormat('Y-m-d H:i', "$date $time", 'Asia/Riyadh');
            } catch (Throwable) {
                abort(422, 'Use booking_date YYYY-MM-DD and slot HH:mm.');
            }
            abort_unless($start->isFuture(), 422, 'The booking time must be in the future.');
            $changes['start_time'] = $start;
        }

        if ($changes !== []) {
            $changes = array_merge($changes, ['quoted_price' => null, 'quoted_duration' => null, 'summary_hash' => null,
                'summary_message_count' => null, 'validated_at' => null]);
            $draft->update($changes);
        }

        $draft = $draft->fresh();
        $result = ['updated' => true, 'draft' => $this->draftData($draft), 'missing_fields' => $this->missingFields($draft)];
        if (array_key_exists('payment_method', $input) && !$draft->note_handled) {
            $result['_action'] = [
                'type' => 'select_note',
                'title' => 'Would you like to add a note?',
                'electronic_payment_warning' => $draft->payment_method === 'electric',
                'options' => [
                    ['label' => 'Add note', 'label_ar' => 'إضافة ملاحظة', 'message' => 'I want to add a note', 'message_ar' => 'أريد إضافة ملاحظة'],
                    ['label' => 'No note', 'label_ar' => 'بدون ملاحظة', 'message' => 'No note', 'message_ar' => 'بدون ملاحظة'],
                ],
            ];
        }
        return $result;
    }

    public function requirements(int $packageId): array
    {
        $package = Package::with('service.attributes')->find($packageId);
        if (!$package) return ['found' => false];
        if (!$package->is_open_package) return ['found' => true, 'is_open_package' => false, 'attributes' => []];
        return [
            'found' => true,
            'is_open_package' => true,
            'attributes' => $package->service->attributes->map(fn ($attribute) => [
                'id' => $attribute->id,
                'name_ar' => $attribute->name_ar,
                'name_en' => $attribute->name_en,
                'type' => $attribute->type,
                'unit_price' => (float) $attribute->pivot->price,
                'unit_duration' => (int) $attribute->pivot->duration,
                'required' => true,
            ])->values()->all(),
            'note' => 'Every Open Package attribute is required and must have a quantity of at least 1 before availability can be loaded.',
        ];
    }

    public function availability(ChatConversation $conversation, User $user, array $arguments = []): array
    {
        $draft = $this->ownedDraft($conversation, $user);
        $packageId = (int) ($arguments['package_id'] ?? $draft->package_id ?? 0);
        $locationId = (int) ($arguments['location_id'] ?? $draft->location_id ?? 0);
        $package = Package::with('service.company')->find($packageId);
        $location = $user->locations()->whereKey($locationId)->first();
        abort_unless($package, 422, 'Select a valid package first.');
        abort_unless($location, 422, 'Select one of your saved locations first.');

        $attributes = $arguments['attributes'] ?? $draft->open_package_attributes ?? [];
        if ($package->is_open_package) {
            $attributes = $this->assertAllOpenAttributes($package->loadMissing('service.attributes'), $attributes);
        }
        $request = Request::create('/chat/availability', $package->is_open_package ? 'POST' : 'GET', [
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'attributes' => $attributes,
        ]);
        $request->setUserResolver(fn () => $user);
        $response = $package->is_open_package
            ? $this->orders->getAvailableSlotsForOpenPackage($package, $request, $this->routes)
            : $this->orders->getAvailableSlots($package, $request, $this->routes);
        $payload = $response->getData(true);
        if ($response->getStatusCode() >= 400) {
            return ['available' => false, 'error' => $payload['message'] ?? 'Availability could not be loaded.'];
        }
        $data = $payload['data'] ?? [];
        $slots = $package->is_open_package ? ($data['slots'] ?? []) : $data;
        if (isset($arguments['date'])) {
            $date = (string) $arguments['date'];
            $slots = [$date => $slots[$date] ?? []];
        }
        $options = collect($slots)->flatMap(fn ($times, $date) => collect($times)->map(fn ($time) => [
            'label' => "$date $time", 'label_ar' => "$date $time", 'message' => "Use $date at $time",
            'message_ar' => "استخدم $date الساعة $time", 'value' => "$date $time",
        ]))->values()->all();
        return [
            'available' => true,
            'package_id' => $package->id,
            'location_id' => $location->id,
            'timezone' => 'Asia/Riyadh',
            'slots' => $slots,
            'total_price' => $package->is_open_package ? ($data['total_price'] ?? null) : (float) ($package->price_after_discount ?? $package->price),
            'duration' => $package->is_open_package ? ($data['duration'] ?? null) : (int) $package->duration,
            '_action' => ['type' => 'select_slot', 'title' => 'Available times', 'options' => $options],
        ];
    }

    public function summary(ChatConversation $conversation, User $user): array
    {
        $draft = $this->ownedDraft($conversation, $user);
        $missing = $this->missingFields($draft);
        if ($missing !== []) return ['valid' => false, 'missing_fields' => $missing, 'draft' => $this->draftData($draft)];

        $package = Package::with('service.company')->find($draft->package_id);
        if (!$package || $package->service_id !== $draft->service_id || $package->service?->company_id !== $draft->company_id) {
            return ['valid' => false, 'error' => 'The selected company, service, and package no longer match. Select them again.'];
        }

        $availability = $this->availability($conversation, $user);
        if (!($availability['available'] ?? false)) return $availability;
        $date = $draft->start_time->format('Y-m-d');
        $time = $draft->start_time->format('H:i');
        if (!in_array($time, $availability['slots'][$date] ?? [], true)) {
            $draft->update(['start_time' => null, 'summary_hash' => null, 'summary_message_count' => null, 'validated_at' => null]);
            return ['valid' => false, 'error' => 'The selected slot is no longer available.', 'available_slots' => $availability['slots'], '_action' => $availability['_action']];
        }

        $draft->load(['company', 'service', 'package', 'location']);
        $summary = $this->summaryData($draft, (float) $availability['total_price'], (int) $availability['duration']);
        $hash = hash('sha256', json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        $draft->update(['quoted_price' => $summary['total'], 'quoted_duration' => $summary['duration_minutes'],
            'summary_hash' => $hash, 'summary_message_count' => $conversation->messages()->count(), 'validated_at' => now()]);
        $action = ['type' => 'booking_summary', 'draft_id' => $draft->id, 'summary' => $summary,
            'confirm_message' => 'Confirm booking', 'change_message' => 'Change booking details',
            'electronic_payment_warning' => $draft->payment_method === 'electric'];
        return ['valid' => true, 'summary' => $summary, 'confirmation_required' => true, '_action' => $action];
    }

    public function create(ChatConversation $conversation, User $user, string $currentMessage): array
    {
        if (!$this->isExplicitConfirmation($currentMessage)) {
            return ['created' => false, 'error' => 'Explicit confirmation is required after the booking summary.'];
        }

        return DB::transaction(function () use ($conversation, $user) {
            $draft = ChatBookingDraft::query()->where('chat_conversation_id', $conversation->id)
                ->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($draft->created_order_id) return $this->createdResult($draft->createdOrder()->firstOrFail());
            abort_unless($draft->summary_hash && $draft->validated_at, 422, 'Show and confirm a current booking summary first.');
            abort_unless($conversation->messages()->count() > (int) $draft->summary_message_count, 422,
                'Confirmation must be sent in a new message after the booking summary.');
            $oldHash = $draft->summary_hash;
            $checked = $this->summary($conversation, $user);
            if (!($checked['valid'] ?? false) || $draft->fresh()->summary_hash !== $oldHash) {
                return ['created' => false, 'error' => 'Booking details, availability, or price changed. Confirm the updated summary.', '_action' => $checked['_action'] ?? null];
            }

            $draft = $draft->fresh(['package', 'location']);
            $body = [
                'package_id' => $draft->package_id,
                'location' => $draft->location->address,
                'latitude' => $draft->location->latitude,
                'longitude' => $draft->location->longitude,
                'start_time' => $draft->start_time->format('Y-m-d H:i:s'),
                'payment_method' => $draft->payment_method,
                'note' => $draft->note,
                'attributes' => $draft->open_package_attributes ?? [],
            ];
            $request = Request::create('/chat/create-order', 'POST', $body);
            $request->setUserResolver(fn () => $user);
            $response = $draft->package->is_open_package
                ? $this->orders->bookOpenPackage($request, $this->routes)
                : $this->orders->store($request, $this->routes);
            $payload = $response->getData(true);
            if ($response->getStatusCode() >= 400) return ['created' => false, 'error' => $payload['message'] ?? 'Booking could not be created.'];
            $orderId = (int) data_get($payload, 'data.id');
            abort_unless($orderId > 0, 500, 'The booking response was invalid.');
            $draft->update(['created_order_id' => $orderId]);
            return $this->createdResult(Order::findOrFail($orderId));
        }, 3);
    }

    private function ownedDraft(ChatConversation $conversation, User $user): ChatBookingDraft
    {
        abort_unless($conversation->user_id === $user->id, 404);
        return ChatBookingDraft::firstOrCreate(
            ['chat_conversation_id' => $conversation->id],
            ['user_id' => $user->id]
        );
    }

    private function draftData(ChatBookingDraft $draft): array
    {
        return [
            'id' => $draft->id, 'company_id' => $draft->company_id, 'service_id' => $draft->service_id,
            'package_id' => $draft->package_id, 'location_id' => $draft->location_id,
            'start_time' => optional($draft->start_time)->toIso8601String(), 'payment_method' => $draft->payment_method,
            'note' => $draft->note, 'note_handled' => $draft->note_handled,
            'open_package_attributes' => $draft->open_package_attributes ?? [],
            'validated' => (bool) $draft->summary_hash, 'created_order_id' => $draft->created_order_id,
            'missing_fields' => $this->missingFields($draft),
        ];
    }

    private function summaryData(ChatBookingDraft $draft, float $price, int $duration): array
    {
        $name = fn ($model) => ['ar' => $model?->name_ar, 'en' => $model?->name_en];
        return [
            'company' => $name($draft->company), 'service' => $name($draft->service),
            'package' => $name($draft->package),
            'location' => ['id' => $draft->location->id, 'name' => $draft->location->name, 'address' => $draft->location->address],
            'date' => $draft->start_time->format('Y-m-d'), 'time' => $draft->start_time->format('H:i'),
            'timezone' => 'Asia/Riyadh', 'payment_method' => $draft->payment_method,
            'note' => $draft->note, 'total' => $price, 'currency' => 'USD', 'duration_minutes' => $duration,
        ];
    }

    private function createdResult(Order $order): array
    {
        $paymentRequired = $order->payment_method === 'electric' && $order->payment_status === 'pending';
        $action = ['type' => $paymentRequired ? 'payment_required' : 'booking_created',
            'order_id' => $order->id, 'payment_method' => $order->payment_method,
            'payment_required' => $paymentRequired, 'electronic_payment_warning' => $paymentRequired];
        return ['created' => true, 'order_id' => $order->id, 'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status, '_action' => $action];
    }

    private function isExplicitConfirmation(string $message): bool
    {
        $value = Str::lower(trim(preg_replace('/[.!،,؟?]+/u', '', $message)));
        return (bool) preg_match('/^(yes|yes confirm|yes confirm booking|yes confirm the booking|confirm|confirm booking|confirm the booking|book it|proceed|نعم|نعم أكد|نعم أكد الحجز|أكد|تأكيد|أكد الحجز|احجز|تابع)$/u', $value);
    }

    private function missingFields(ChatBookingDraft $draft): array
    {
        $missing = collect([
            'company' => $draft->company_id,
            'service' => $draft->service_id,
            'package' => $draft->package_id,
            'location' => $draft->location_id,
            'date' => $draft->start_time,
            'time' => $draft->start_time,
            'payment_method' => $draft->payment_method,
            'note_or_skip_note' => $draft->note_handled,
        ])->filter(fn ($value) => !$value)->keys()->all();

        if ($draft->package_id) {
            $package = Package::with('service.attributes')->find($draft->package_id);
            if ($package?->is_open_package) {
                $state = $this->openAttributeState($package, $draft->open_package_attributes ?? []);
                if ($state['missing'] !== []) $missing[] = 'open_package_attributes';
            }
        }

        return array_values(array_unique($missing));
    }

    private function assertAllOpenAttributes(Package $package, array $attributes): array
    {
        $state = $this->openAttributeState($package, $attributes);
        abort_if($state['invalid'] !== [], 422, 'Open Package attributes contain invalid values.');
        abort_if($state['missing'] !== [], 422,
            'Complete every Open Package attribute before continuing: '.implode(', ', $state['missing_names']));
        return $state['attributes'];
    }

    private function openAttributeState(Package $package, array $attributes): array
    {
        $allowed = $package->service->attributes->keyBy('id');
        $provided = collect($attributes)->map(fn ($item) => [
            'id' => (int) ($item['id'] ?? 0),
            'qty' => (int) ($item['qty'] ?? 0),
        ])->keyBy('id');
        $invalid = $provided->filter(fn ($item, $id) => !$allowed->has($id) || $item['qty'] < 1)->keys()->all();
        $missing = $allowed->keys()->diff($provided->filter(fn ($item) => $item['qty'] >= 1)->keys())->values();
        return [
            'attributes' => $provided->values()->all(),
            'invalid' => $invalid,
            'missing' => $missing->all(),
            'missing_names' => $missing->map(fn ($id) => $allowed[$id]->name_en ?? $allowed[$id]->name_ar ?? "Attribute $id")->all(),
        ];
    }
}
