<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'name' => $this->{"name_$lang"},
            'description' => $this->{"description_$lang"},
            'image' => $this->image,

            // لن تظهر إلا إذا استدعيت $category->load('services') في الـ Show
            'services' => ServiceResource::collection($this->whenLoaded('services')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
