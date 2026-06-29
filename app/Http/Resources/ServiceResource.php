<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
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
        'company_id' => $this->company_id,
        'category_id' => $this->category_id,
        'name' => $this->{"name_$lang"},
        'description' => $this->{"description_$lang"},
        'rating' => (float) $this->rating,
        'min_duration' => $this->min_duration,
        'max_duration' => $this->max_duration,
        'price' => (float) $this->price,
        'image' => $this->image,
        'discount' => (float) $this->discount,

        // العلاقات
        'company' => new CompanyResource($this->whenLoaded('company')),
        'packages' => PackageResource::collection($this->whenLoaded('packages')),
        'attributes' => AttributeResource::collection($this->whenLoaded('attributes')),
        'images' => ServiceImageResource::collection($this->whenLoaded('images')), 
        'reviews' => $this->whenLoaded('reviews'),

        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}

}
