<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Region extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'manager_id','image'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/' . $value) : null,
        );
    }
}
