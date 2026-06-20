<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    protected $fillable = ['client_id', 'comment', 'rating', 'reviewable_id', 'reviewable_type'];

    // --- Relationships ---

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Morph target loader identifying structural targets (Service / Company).
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}
