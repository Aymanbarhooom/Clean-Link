<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceImage extends Model
{
    protected $fillable = ['service_id', 'name', 'images'];

    protected $casts = [
        'images' => 'array',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
