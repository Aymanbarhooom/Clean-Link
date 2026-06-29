<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
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
        'manager_id' => $this->manager_id,
        'image' => $this->image,

        'manager' => $this->whenLoaded('manager'),
        'companies' => CompanyResource::collection($this->whenLoaded('companies')),

        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}

}
