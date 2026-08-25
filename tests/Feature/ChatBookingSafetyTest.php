<?php

namespace Tests\Feature;

use App\Services\Chat\ChatBookingService;
use App\Services\Chat\CleanLinkChatTools;
use App\Services\Chat\CleanLinkChatInstructions;
use App\Models\AttributeModel;
use App\Models\ChatBookingDraft;
use App\Models\Package;
use App\Models\Service;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class ChatBookingSafetyTest extends TestCase
{
    public function test_booking_tools_are_explicit_and_order_creation_accepts_no_raw_order_fields(): void
    {
        $declarations = collect(CleanLinkChatTools::definitions()[0]['functionDeclarations'])->keyBy('name');

        foreach (['get_booking_draft', 'update_booking_draft', 'get_available_slots',
            'validate_booking_draft', 'get_booking_summary', 'create_order_from_booking_draft'] as $name) {
            $this->assertTrue($declarations->has($name), "Missing tool: {$name}");
        }

        $create = $declarations->get('create_order_from_booking_draft');
        $this->assertSame([], (array) $create['parameters']['properties']);
        $this->assertArrayNotHasKey('price', (array) $create['parameters']['properties']);
        $this->assertArrayNotHasKey('user_id', (array) $create['parameters']['properties']);
    }

    #[DataProvider('confirmationProvider')]
    public function test_only_explicit_confirmation_phrases_are_accepted(string $message, bool $expected): void
    {
        $reflection = new ReflectionClass(ChatBookingService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('isExplicitConfirmation');

        $this->assertSame($expected, $method->invoke($service, $message));
    }

    public static function confirmationProvider(): array
    {
        return [
            ['Confirm booking', true], ['Yes', true], ['Yes, confirm the booking', true], ['Book it', true], ['نعم', true], ['أكد الحجز', true],
            ['Maybe', false], ['Show another time', false], ['Thanks', false], ['How much?', false],
        ];
    }

    public function test_every_core_booking_selection_is_required(): void
    {
        $reflection = new ReflectionClass(ChatBookingService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('missingFields');

        $missing = $method->invoke($service, new ChatBookingDraft());

        $this->assertSame([
            'company', 'service', 'package', 'location', 'date', 'time',
            'payment_method', 'note_or_skip_note',
        ], $missing);
    }

    public function test_open_package_state_requires_every_real_attribute(): void
    {
        $first = new AttributeModel(['name_en' => 'Rooms']);
        $first->id = 11;
        $second = new AttributeModel(['name_en' => 'Bathrooms']);
        $second->id = 12;
        $serviceModel = new Service();
        $serviceModel->setRelation('attributes', collect([$first, $second]));
        $package = new Package();
        $package->setRelation('service', $serviceModel);

        $reflection = new ReflectionClass(ChatBookingService::class);
        $bookingService = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('openAttributeState');
        $state = $method->invoke($bookingService, $package, [['id' => 11, 'qty' => 2]]);

        $this->assertSame([12], $state['missing']);
        $this->assertSame(['Bathrooms'], $state['missing_names']);
        $this->assertSame([], $state['invalid']);
    }

    public function test_instructions_enforce_the_progressive_booking_flow_and_card_deadline(): void
    {
        $instructions = CleanLinkChatInstructions::text();

        $this->assertStringContainsString('Call search_companies with an empty query', $instructions);
        $this->assertStringContainsString('All Open Package attributes are mandatory', $instructions);
        $this->assertStringContainsString('payment must be confirmed within 10 minutes', $instructions);
        $this->assertStringContainsString('company, service, package, saved location, date, time, payment method', $instructions);
    }
}
