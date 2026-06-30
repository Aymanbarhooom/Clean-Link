<?php

// app/Models/ServiceImage.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;


class ServiceImage extends Model 
{
    protected $fillable = ['service_id', 'image_before', 'image_after'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/' . $value) : null,
        );
    }
}
