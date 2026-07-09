<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'title' => $lang === 'en' ? $this->title_en : $this->title_ar,
            'body' => $lang === 'en' ? $this->body_en : $this->body_ar,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at,
        ];
    }
}
