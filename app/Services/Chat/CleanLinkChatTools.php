<?php

namespace App\Services\Chat;

class CleanLinkChatTools
{
    public static function definitions(): array
    {
        $id = fn (string $key) => ['type' => 'OBJECT', 'properties' => [$key => ['type' => 'INTEGER']], 'required' => [$key]];
        $query = ['type' => 'OBJECT', 'properties' => ['query' => ['type' => 'STRING', 'nullable' => true]]];
        $none = ['type' => 'OBJECT', 'properties' => new \stdClass()];
        $declarations = [
            self::tool('search_categories', 'Search client-visible CleanLink categories.', $query),
            self::tool('get_category_details', 'Get a category and its services.', $id('category_id')),
            self::tool('search_regions', 'Search CleanLink regions.', $query),
            self::tool('get_region_details', 'Get a region and its companies.', $id('region_id')),
            self::tool('search_companies', 'Search companies by name or service.', ['type' => 'OBJECT', 'properties' => [
                'query' => ['type' => 'STRING', 'nullable' => true], 'service_query' => ['type' => 'STRING', 'nullable' => true]]]),
            self::tool('get_company_details', 'Get safe current company details.', $id('company_id')),
            self::tool('compare_companies', 'Compare selected companies; location must belong to the client.', ['type' => 'OBJECT',
                'properties' => ['company_ids' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
                    'location_id' => ['type' => 'INTEGER', 'nullable' => true]], 'required' => ['company_ids']]),
            self::tool('find_nearby_companies', 'Find companies near a saved client location.', $id('location_id')),
            self::tool('search_services', 'Search client-visible services.', $query),
            self::tool('get_service_details', 'Get service, company, category, and packages.', $id('service_id')),
            self::tool('get_company_services', 'Get services for one company.', $id('company_id')),
            self::tool('get_service_packages', 'Get packages for one service.', $id('service_id')),
            self::tool('get_package_details', 'Get current package details and price.', $id('package_id')),
            self::tool('get_open_package_requirements', 'Get every mandatory Open Package attribute and backend unit price/duration. Collect qty >= 1 for all returned attributes.', $id('package_id')),
            self::tool('get_my_locations', 'Get saved locations owned by this client.', $none),
            self::tool('get_location_details', 'Get one saved location owned by this client.', $id('location_id')),
            self::tool('get_available_dates', 'Get dates containing real backend availability.', self::availabilitySchema()),
            self::tool('get_available_slots', 'Get real backend-computed slots. Open Packages require every attribute first. Never calculate slots yourself.', self::availabilitySchema()),
            self::tool('get_my_orders', 'Get recent orders owned by this client.', ['type' => 'OBJECT', 'properties' => ['status' => ['type' => 'STRING', 'nullable' => true]]]),
            self::tool('get_my_last_order', 'Get the latest order owned by this client.', $none),
            self::tool('get_my_order', 'Get one order owned by this client.', $id('order_id')),
            self::tool('get_payment_methods', 'Get actual supported booking payment methods.', $none),
            self::tool('get_reviews', 'Get public reviews for a company or service.', ['type' => 'OBJECT', 'properties' => [
                'type' => ['type' => 'STRING', 'enum' => ['company', 'service']], 'id' => ['type' => 'INTEGER']], 'required' => ['type', 'id']]),
            self::tool('get_company_reviews', 'Get public reviews for a company.', $id('company_id')),
            self::tool('get_current_offers', 'Get current services with discounts.', $none),
            self::tool('get_my_favorites', 'Get favorites owned by this client.', $none),
            self::tool('get_booking_draft', 'Get the server-owned booking draft for this conversation.', $none),
            self::tool('update_booking_draft', 'Validate and update only allowed booking selections in company, service, package, all Open Package attributes, location, date/time, payment, note order. Changing dependencies resets later fields.', [
                'type' => 'OBJECT', 'properties' => [
                    'company_id' => ['type' => 'INTEGER', 'nullable' => true], 'service_id' => ['type' => 'INTEGER', 'nullable' => true],
                    'package_id' => ['type' => 'INTEGER', 'nullable' => true], 'location_id' => ['type' => 'INTEGER', 'nullable' => true],
                    'booking_date' => ['type' => 'STRING', 'description' => 'YYYY-MM-DD', 'nullable' => true],
                    'slot' => ['type' => 'STRING', 'description' => 'HH:mm', 'nullable' => true],
                    'payment_method' => ['type' => 'STRING', 'nullable' => true], 'note' => ['type' => 'STRING', 'nullable' => true],
                    'skip_note' => ['type' => 'BOOLEAN', 'nullable' => true],
                    'attributes' => ['type' => 'ARRAY', 'nullable' => true, 'items' => ['type' => 'OBJECT', 'properties' => [
                        'id' => ['type' => 'INTEGER'], 'qty' => ['type' => 'INTEGER']], 'required' => ['id', 'qty']]],
                ]]),
            self::tool('validate_booking_draft', 'Revalidate all draft dependencies, live slots, and backend price, then return the final summary.', $none),
            self::tool('get_booking_summary', 'Return a freshly validated final booking summary.', $none),
            self::tool('create_order_from_booking_draft', 'Create exactly one order from the validated server draft. Call only after explicit confirmation in the current user message.', $none),
        ];
        return [['functionDeclarations' => $declarations]];
    }

    private static function tool(string $name, string $description, array $parameters): array
    {
        return compact('name', 'description', 'parameters');
    }

    private static function availabilitySchema(): array
    {
        return ['type' => 'OBJECT', 'properties' => [
            'package_id' => ['type' => 'INTEGER', 'nullable' => true], 'location_id' => ['type' => 'INTEGER', 'nullable' => true],
            'date' => ['type' => 'STRING', 'description' => 'Optional YYYY-MM-DD', 'nullable' => true],
            'attributes' => ['type' => 'ARRAY', 'nullable' => true, 'items' => ['type' => 'OBJECT', 'properties' => [
                'id' => ['type' => 'INTEGER'], 'qty' => ['type' => 'INTEGER']], 'required' => ['id', 'qty']]],
        ]];
    }
}
