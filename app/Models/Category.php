<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Category extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'description_ar', 'description_en', 'image'];
    protected $appends = ['name', 'description'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? asset('storage/' . $value) : null,
        );
    }

}
