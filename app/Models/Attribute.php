<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attribute extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'type'];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'attribute_service')
                    ->withPivot('price', 'duration');
    }
}
