<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderLocationResource extends JsonResource
{
    /**
     * Transform an order into the lightweight map-marker representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lang = $request->header('Accept-Language', 'ar');

        return [
            'id' => $this->id,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'status' => $this->status,
            'location' => $this->location,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'total_price' => (float) $this->total_price,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'fullname' => $this->client->fullname,
            ]),
            'package' => $this->whenLoaded('package', fn () => [
                'id' => $this->package->id,
                'name' => $this->package->{"name_$lang"} ?? $this->package->name_en ?? $this->package->name_ar,
            ]),
            'service' => $this->when(
                $this->relationLoaded('package') && $this->package?->relationLoaded('service'),
                fn () => [
                    'id' => $this->package->service->id,
                    'name' => $this->package->service->{"name_$lang"}
                        ?? $this->package->service->name_en
                        ?? $this->package->service->name_ar,
                ]
            ),
        ];
    }
}
