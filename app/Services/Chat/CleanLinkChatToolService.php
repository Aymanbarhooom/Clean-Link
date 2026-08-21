<?php


namespace App\Services\Chat;


use App\Models\Company;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;


class CleanLinkChatToolService
{
    public function __construct(
        private readonly DistanceService $distanceService
    ) {
    }


    public function execute(
        string $name,
        array $arguments,
        User $user
    ): array {
        return match ($name) {
            'get_my_locations' =>
                $this->getMyLocations($user),


            'search_companies' =>
                $this->searchCompanies($arguments),


            'get_company_details' =>
                $this->getCompanyDetails($arguments),


            'compare_companies' =>
                $this->compareCompanies(
                    $arguments,
                    $user
                ),


            'search_services' =>
                $this->searchServices($arguments),


            'get_service_details' =>
                $this->getServiceDetails($arguments),


            'find_nearby_companies' =>
                $this->findNearbyCompanies(
                    $arguments,
                    $user
                ),


            'get_my_last_order' =>
                $this->getMyLastOrder($user),


            'get_my_order' =>
                $this->getMyOrder(
                    (int) ($arguments['order_id'] ?? 0),
                    $user
                ),


            default => [
                'error' => 'Unknown CleanLink tool.',
            ],
        };
    }


    private function getMyLocations(
        User $user
    ): array {
        $locations = $user
            ->locations()
            ->get()
            ->map(function ($location) {
                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'address' => $location->address,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                ];
            })
            ->values();


        return [
            'count' => $locations->count(),
            'locations' => $locations->toArray(),
        ];
    }


    private function searchCompanies(
        array $arguments
    ): array {
        $queryText = trim(
            (string) ($arguments['query'] ?? '')
        );


        $serviceQuery = trim(
            (string) ($arguments['service_query'] ?? '')
        );


        $query = Company::query()
            ->with('services');


        if ($queryText !== '') {
            $query->where(
                'name',
                'like',
                '%' . $queryText . '%'
            );
        }


        if ($serviceQuery !== '') {
            $query->whereHas(
                'services',
                function ($builder) use ($serviceQuery) {
                    $builder->where(
                        'name',
                        'like',
                        '%' . $serviceQuery . '%'
                    );
                }
            );
        }


        $companies = $query
            ->limit(20)
            ->get()
            ->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'rating' => $company->rating ?? null,
                    'latitude' => $company->latitude ?? null,
                    'longitude' => $company->longitude ?? null,
                    'services' => $company
                        ->services
                        ->map(function ($service) {
                            return [
                                'id' => $service->id,
                                'name' => $service->name,
                                'min_price' =>
                                    $service->min_price ?? null,
                                'max_price' =>
                                    $service->max_price ?? null,
                            ];
                        })
                        ->values()
                        ->toArray(),
                ];
            })
            ->values();


        return [
            'count' => $companies->count(),
            'companies' => $companies->toArray(),
        ];
    }


    private function getCompanyDetails(
        array $arguments
    ): array {
        $companyId = (int) (
            $arguments['company_id'] ?? 0
        );


        $company = Company::query()
            ->with([
                'services',
            ])
            ->find($companyId);


        if (!$company) {
            return [
                'found' => false,
            ];
        }


        return [
            'found' => true,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'rating' => $company->rating ?? null,
                'latitude' => $company->latitude ?? null,
                'longitude' => $company->longitude ?? null,
                'services' => $company
                    ->services
                    ->map(function ($service) {
                        return [
                            'id' => $service->id,
                            'name' => $service->name,
                            'min_price' =>
                                $service->min_price ?? null,
                            'max_price' =>
                                $service->max_price ?? null,
                        ];
                    })
                    ->values()
                    ->toArray(),
            ],
        ];
    }


    private function compareCompanies(
        array $arguments,
        User $user
    ): array {
        $companyIds = collect(
            $arguments['company_ids'] ?? []
        )
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();


        if ($companyIds->isEmpty()) {
            return [
                'error' => 'No companies were provided.',
            ];
        }


        $serviceQuery = trim(
            (string) ($arguments['service_query'] ?? '')
        );


        $locationId =
            isset($arguments['location_id'])
                ? (int) $arguments['location_id']
                : null;


        $location = null;


        if ($locationId) {
            $location = $user
                ->locations()
                ->whereKey($locationId)
                ->first();


            if (!$location) {
                return [
                    'error' =>
                        'Location does not belong to the authenticated client.',
                ];
            }
        }


        $companies = Company::query()
            ->with('services')
            ->whereIn(
                'id',
                $companyIds->all()
            )
            ->get();


        $result = $companies
            ->map(function ($company) use (
                $serviceQuery,
                $location
            ) {
                $services = $company->services;


                if ($serviceQuery !== '') {
                    $services = $services
                        ->filter(function ($service) use (
                            $serviceQuery
                        ) {
                            return str_contains(
                                mb_strtolower($service->name),
                                mb_strtolower($serviceQuery)
                            );
                        });
                }


                $distance = null;


                if (
                    $location &&
                    $location->latitude !== null &&
                    $location->longitude !== null &&
                    $company->latitude !== null &&
                    $company->longitude !== null
                ) {
                    $distance =
                        $this->distanceService
                            ->calculateKm(
                                (float) $location->latitude,
                                (float) $location->longitude,
                                (float) $company->latitude,
                                (float) $company->longitude
                            );
                }


                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'rating' => $company->rating ?? null,
                    'distance_km' => $distance,
                    'services' => $services
                        ->map(function ($service) {
                            return [
                                'id' => $service->id,
                                'name' => $service->name,
                                'min_price' =>
                                    $service->min_price ?? null,
                                'max_price' =>
                                    $service->max_price ?? null,
                            ];
                        })
                        ->values()
                        ->toArray(),
                ];
            })
            ->values();


        return [
            'location' => $location
                ? [
                    'id' => $location->id,
                    'name' => $location->name,
                ]
                : null,
            'companies' => $result->toArray(),
        ];
    }


    private function searchServices(
        array $arguments
    ): array {
        $search = trim(
            (string) ($arguments['query'] ?? '')
        );


        $services = Service::query()
            ->where(
                'name',
                'like',
                '%' . $search . '%'
            )
            ->limit(20)
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'min_price' =>
                        $service->min_price ?? null,
                    'max_price' =>
                        $service->max_price ?? null,
                ];
            })
            ->values();


        return [
            'count' => $services->count(),
            'services' => $services->toArray(),
        ];
    }


    private function getServiceDetails(
        array $arguments
    ): array {
        $serviceId = (int) (
            $arguments['service_id'] ?? 0
        );


        $service = Service::query()
            ->with([
                'companies',
                'packages',
            ])
            ->find($serviceId);


        if (!$service) {
            return [
                'found' => false,
            ];
        }


        return [
            'found' => true,
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'min_price' =>
                    $service->min_price ?? null,
                'max_price' =>
                    $service->max_price ?? null,
                'companies' => $service
                    ->companies
                    ->map(function ($company) {
                        return [
                            'id' => $company->id,
                            'name' => $company->name,
                            'rating' =>
                                $company->rating ?? null,
                        ];
                    })
                    ->values()
                    ->toArray(),
                'packages' => $service
                    ->packages
                    ->map(function ($package) {
                        return [
                            'id' => $package->id,
                            'name' => $package->name,
                            'price' =>
                                $package->price ?? null,
                        ];
                    })
                    ->values()
                    ->toArray(),
            ],
        ];
    }


    private function findNearbyCompanies(
        array $arguments,
        User $user
    ): array {
        $locationId = (int) (
            $arguments['location_id'] ?? 0
        );


        $serviceQuery = trim(
            (string) ($arguments['service_query'] ?? '')
        );


        $location = $user
            ->locations()
            ->whereKey($locationId)
            ->first();


        if (!$location) {
            return [
                'error' =>
                    'Location does not belong to the authenticated client.',
            ];
        }


        $query = Company::query()
            ->with('services');


        if ($serviceQuery !== '') {
            $query->whereHas(
                'services',
                function ($builder) use ($serviceQuery) {
                    $builder->where(
                        'name',
                        'like',
                        '%' . $serviceQuery . '%'
                    );
                }
            );
        }


        $companies = $query
            ->limit(50)
            ->get()
            ->map(function ($company) use ($location) {
                $distance = null;


                if (
                    $location->latitude !== null &&
                    $location->longitude !== null &&
                    $company->latitude !== null &&
                    $company->longitude !== null
                ) {
                    $distance =
                        $this->distanceService
                            ->calculateKm(
                                (float) $location->latitude,
                                (float) $location->longitude,
                                (float) $company->latitude,
                                (float) $company->longitude
                            );
                }


                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'rating' =>
                        $company->rating ?? null,
                    'distance_km' => $distance,
                    'services' => $company
                        ->services
                        ->map(function ($service) {
                            return [
                                'id' => $service->id,
                                'name' => $service->name,
                                'min_price' =>
                                    $service->min_price ?? null,
                                'max_price' =>
                                    $service->max_price ?? null,
                            ];
                        })
                        ->values()
                        ->toArray(),
                ];
            })
            ->sortBy(
                fn ($company) =>
                    $company['distance_km']
                    ?? PHP_FLOAT_MAX
            )
            ->take(10)
            ->values();


        return [
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'address' =>
                    $location->address ?? null,
            ],
            'companies' => $companies->toArray(),
        ];
    }


    private function getMyLastOrder(
        User $user
    ): array {
        $order = Order::query()
            ->where(
                'client_id',
                $user->id
            )
            ->latest()
            ->first();


        if (!$order) {
            return [
                'found' => false,
            ];
        }


        return $this->orderData($order);
    }


    private function getMyOrder(
        int $orderId,
        User $user
    ): array {
        $order = Order::query()
            ->where(
                'id',
                $orderId
            )
            ->where(
                'client_id',
                $user->id
            )
            ->first();


        if (!$order) {
            return [
                'found' => false,
            ];
        }


        return $this->orderData($order);
    }


    private function orderData(
        Order $order
    ): array {
        return [
            'found' => true,
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'payment_method' =>
                    $order->payment_method ?? null,
                'payment_status' =>
                    $order->payment_status ?? null,
                'location' =>
                    $order->location ?? null,
                'start_time' =>
                    $order->start_time ?? null,
                'end_time' =>
                    $order->end_time ?? null,
                'created_at' =>
                    $order->created_at?->toISOString(),
            ],
        ];
    }
}
