<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'description_ar', 'description_en', 'image'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
