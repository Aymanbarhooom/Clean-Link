<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'package_id' => $this->package_id,
            'status' => $this->status,
            'location' => $this->location,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration' => $this->duration,
            'total_price' => $this->total_price ,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            //relationships
            'client' => $this->whenLoaded('client'),
            'package' => new PackageResource($this->package),
            'attributes' => AttributeResource::collection($this->whenLoaded('attributes')),
        ];
    }
}
