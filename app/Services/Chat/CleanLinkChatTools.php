<?php

namespace App\Services\Chat;

class CleanLinkChatTools
{
    public static function definitions(): array
    {
        return [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'get_my_locations',
                        'description' => 'Get saved locations belonging to the authenticated CleanLink client.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => new \stdClass(),
                        ],
                    ],
                    [
                        'name' => 'search_companies',
                        'description' => 'Search current CleanLink companies by company name or service.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => [
                                    'type' => 'STRING',
                                    'description' => 'Company search text.',
                                    'nullable' => true,
                                ],
                                'service_query' => [
                                    'type' => 'STRING',
                                    'description' => 'Optional service name.',
                                    'nullable' => true,
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_company_details',
                        'description' => 'Get current details about one CleanLink company.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'company_id' => [
                                    'type' => 'INTEGER',
                                ],
                            ],
                            'required' => [
                                'company_id',
                            ],
                        ],
                    ],
                    [
                        'name' => 'compare_companies',
                        'description' => 'Compare CleanLink companies using current service, price, rating and optional distance information.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'company_ids' => [
                                    'type' => 'ARRAY',
                                    'items' => [
                                        'type' => 'INTEGER',
                                    ],
                                ],
                                'service_query' => [
                                    'type' => 'STRING',
                                    'nullable' => true,
                                ],
                                'location_id' => [
                                    'type' => 'INTEGER',
                                    'nullable' => true,
                                ],
                            ],
                            'required' => [
                                'company_ids',
                            ],
                        ],
                    ],
                    [
                        'name' => 'search_services',
                        'description' => 'Search current CleanLink services.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => [
                                    'type' => 'STRING',
                                ],
                            ],
                            'required' => [
                                'query',
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_service_details',
                        'description' => 'Get current CleanLink service information, companies and packages.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'service_id' => [
                                    'type' => 'INTEGER',
                                ],
                            ],
                            'required' => [
                                'service_id',
                            ],
                        ],
                    ],
                    [
                        'name' => 'find_nearby_companies',
                        'description' => 'Find CleanLink companies near one saved client location and optionally filter them by service.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'location_id' => [
                                    'type' => 'INTEGER',
                                ],
                                'service_query' => [
                                    'type' => 'STRING',
                                    'nullable' => true,
                                ],
                            ],
                            'required' => [
                                'location_id',
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_my_last_order',
                        'description' => 'Get the latest CleanLink order belonging to the authenticated client.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => new \stdClass(),
                        ],
                    ],
                    [
                        'name' => 'get_my_order',
                        'description' => 'Get one CleanLink order belonging to the authenticated client.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'order_id' => [
                                    'type' => 'INTEGER',
                                ],
                            ],
                            'required' => [
                                'order_id',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}