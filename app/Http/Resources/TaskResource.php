<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return[
         'order_id' => $this->order_id,
        'workgroup_id' => $this->workgroup_id,
        'status' => $this->status,
        'image_before' => $this->image_before ? asset('storage/' . $this->image_before) : null,
        'image_after' => $this->image_after ? asset('storage/' . $this->image_after) : null,
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
        'order' => new OrderResource($this->whenLoaded('order')),
        'workgroup' => $this->whenLoaded('workgroup'),];
    }
}
