<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lang = $request->header('Accept-Language', 'ar');

        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'name' => $this->{"name_$lang"},
            'duration' => $this->duration,
            'price' => (float) $this->price,
            'price_after_discount' => (float) $this->price_after_discount,
            'details' => is_string($this->{"details_$lang"}) ? json_decode($this->{"details_$lang"}, true) : $this->{"details_$lang"},
            'minimum_workers' => $this->minimum_workers,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // العلاقات
            'service' => new ServiceResource($this->whenLoaded('service')),
        ];
    }
}
