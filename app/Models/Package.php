<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = ['service_id', 'name_ar', 'name_en', 'duration', 'price','price_after_discount', 'details_ar', 'details_en'];

    /**
     * Automatic JSON casting conversion for array structures.
     */
    protected $casts = [
        'details_ar' => 'array',
        'details_en' => 'array'
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
