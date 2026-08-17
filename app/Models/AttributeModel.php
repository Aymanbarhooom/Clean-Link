<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttributeModel extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'type'];
    protected $table = 'attributes';


    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'attribute_service', 'attribute_id', 'service_id')
            ->withPivot('price', 'duration');
    }


    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'attribute_order', 'attribute_id', 'order_id')
            ->withPivot('qty', 'price_at_order')
            ->withTimestamps();
    }

}
