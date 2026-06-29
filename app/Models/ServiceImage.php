<?php

// app/Models/ServiceImage.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;


class ServiceImage extends Model 
{
    protected $fillable = ['service_id', 'name', 'images'];

    /**
     * Automatic JSON casting conversion for array handling structures.
     */
    protected $casts = [
        'images' => 'array'
    ];

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
