<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    protected $fillable = [
        'client_id',
        'title',
        'body',
        'is_read',
        'read_at',
        'complaintable_id',
        'complaintable_type'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // Relationships
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function complaintable(): MorphTo
    {
        return $this->morphTo();
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ComplaintResponse::class)->orderBy('created_at', 'asc');
    }

    public function latestResponse()
    {
        return $this->hasOne(ComplaintResponse::class)->latest();
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeForCompany($query)
    {
        return $query->where('complaintable_type', Company::class);
    }

    public function scopeForService($query)
    {
        return $query->where('complaintable_type', Service::class);
    }

    // Mark as read
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    // Mark as unread
    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    // Check if complaint has responses
    public function hasResponses(): bool
    {
        return $this->responses()->exists();
    }

    // Get external responses only (for client)
    public function getExternalResponses()
    {
        return $this->responses()->where('is_internal', false)->get();
    }
}