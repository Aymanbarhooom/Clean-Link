<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'client_id', 'package_id',
        'note', 'location', 'time', 'status', 'total_price'
    ];

    // --- Relationships ---

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function attributes(): BelongsToMany
{
    return $this->belongsToMany(AttributeModel::class, 'attribute_order', 'order_id', 'attribute_id')
                ->withPivot('qty', 'price_at_order')
                ->withTimestamps();
}


    // --- Helper Functions ---

    /**
     * Compute invoice total based on the selected package price and dynamic attributes.
     */
    public function calculateAndSetTotalPrice(): void
    {
        $basePrice = $this->package->price;
        
        $addonsPrice = $this->attributes()->get()->sum(function ($attribute) {
            return $attribute->pivot->qty * $attribute->pivot->price_at_order;
        });

        $this->update(['total_price' => $basePrice + $addonsPrice]);
    }

    public function isAssigned(): bool
    {
        return $this->status === 'assigned_to_worker';
    }
}
