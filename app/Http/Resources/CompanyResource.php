<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
        'manager_id' => $this->manager_id,
        'region_id' => $this->region_id,
        'name' => $this->{"name_$lang"},
        'description' => $this->{"description_$lang"},
        'image' => $this->image,
        'location' => $this->{"location_$lang"},
        'rating' => (float) $this->rating,
        'is_favorite' => $this->is_favorite,

        'manager' => $this->whenLoaded('manager'), 
        'region' => new RegionResource($this->whenLoaded('region')),
        'services' => ServiceResource::collection($this->whenLoaded('services')),
        'workers' => $this->whenLoaded('workers'),
        'reviews' => $this->whenLoaded('reviews'), // يفضل عمل ReviewResource لاحقاً
        'workTimes' => $this->whenLoaded('workTimes'),

        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}

}
