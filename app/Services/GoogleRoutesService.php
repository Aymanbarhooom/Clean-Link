<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleRoutesService
{
    private string $apiKey;
    private string $endpoint = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public function __construct()
    {
        $this->apiKey = config('services.google.routes_api_key');
    }

    
    public function calculateDrivingRoute(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): ?int {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => 'routes.duration',
            ])->timeout(20)->post($this->endpoint, [
                'origin' => [
                    'location' => [
                        'latLng' => [
                            'latitude' => $originLat,
                            'longitude' => $originLng,
                        ],
                    ],
                ],
                'destination' => [
                    'location' => [
                        'latLng' => [
                            'latitude' => $destLat,
                            'longitude' => $destLng,
                        ],
                    ],
                ],
                'travelMode' => 'DRIVE',
                'routingPreference' => 'TRAFFIC_UNAWARE', 
            ]);
             \Log::info('GoogleRoutesService DEBUG', [
            'api_key_present' => !empty($this->apiKey),
            'status' => $response->status(),
            'body' => $response->body(),]);

            if (!$response->successful()) {
                Log::warning('GoogleRoutesService: API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $durationStr = $data['routes'][0]['duration'] ?? null;

            if (!$durationStr) {
                Log::warning('GoogleRoutesService: no duration in response', ['response' => $data]);
                return null;
            }

            $seconds = (int) rtrim($durationStr, 's');
            return (int) ceil($seconds / 60); 

        } catch (\Throwable $e) {
            Log::error('GoogleRoutesService: exception during route calculation', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}