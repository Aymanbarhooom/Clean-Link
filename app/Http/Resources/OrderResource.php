<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
            'travel_buffer_minutes' => $this->travel_buffer_minutes,
            'total_price' => $this->total_price ,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            //relationships
            'client' => $this->whenLoaded('client'),
            'leader' => $this->getLeaderResource(),
            'package' => new PackageResource($this->package),
            'attributes' => $this->whenLoaded('attributes', function () use ($request) {
                $lang = $request->header('Accept-Language', 'ar');
                $serviceId = $this->package?->service?->id;

                return $this->attributes->map(function ($attr) use ($lang, $serviceId) {
                    $qty = $attr->pivot->qty ?? $attr->pivot->quantity ?? null;

                    $price = null;
                    $duration = null;
                    if ($serviceId) {
                        $row = DB::table('attribute_service')
                            ->where('attribute_id', $attr->id)
                            ->where('service_id', $serviceId)
                            ->first();

                        if ($row) {
                            $price = (float) $row->price;
                            $duration = (int) $row->duration;
                        }
                    }

                    return [
                        'id' => $attr->id,
                        'name' => $attr->{"name_$lang"} ?? $attr->name_en ?? $attr->name_ar,
                        'type' => $attr->type,
                        'price' => $price,
                        'duration' => $duration,
                        'qty' => $qty,
                        'created_at' => $attr->created_at,
                        'updated_at' => $attr->updated_at,
                    ];
                })->values();
            }),
        ];
    }

    private function getLeaderResource(): ?UserResource
    {
        if ($this->relationLoaded('leader') && $this->leader) {
            return new UserResource($this->leader);
        }

        if ($this->relationLoaded('tasks')) {
            $task = $this->tasks->first();
            $leader = $task?->workgroup?->leader;
            if ($leader) {
                return new UserResource($leader);
            }
        }

        return null;
    }
}
