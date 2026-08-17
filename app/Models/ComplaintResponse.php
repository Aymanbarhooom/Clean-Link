<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintResponse extends Model
{
    protected $fillable = [
        'complaint_id',
        'responder_id',
        'response',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    // Relationships
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responder_id');
    }

    public function scopeExternal($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }
}