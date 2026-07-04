<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Task extends Model
{
    protected $fillable = ['order_id', 'workgroup_id', 'status', 'image_before', 'image_after'];

    // --- Relationships ---

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function workgroup(): BelongsTo
    {
        return $this->belongsTo(Workgroup::class);
    }

    
    public function advanceStatus(string $newStatus): bool
    {
        if (!in_array($newStatus, ['on_way', 'handling', 'done'])) {
            return false;
        }

        $this->update(['status' => $newStatus]);

        if ($newStatus === 'done') {
            $this->order()->update(['status' => 'completed']);
        }

        return true;
    } 

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? asset('storage/' . $value) : null,
        );
    }
}
