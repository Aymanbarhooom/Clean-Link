<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Map over the casted array to prepend the asset path to each file
        $fullUrls = collect($this->images)->map(function ($path) {
            return $path ? asset('storage/' . $path) : null;
        })->all();

        return [
            'id'         => $this->id,
            'service_id' => $this->service_id,
            'name'       => $this->name,
            'images'     => $fullUrls, // Returns full asset links
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
