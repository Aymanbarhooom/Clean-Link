<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Company extends Model
{
    protected $fillable = [
         'manager_id', 'region_id', 'name_ar', 'name_en', 
        'description_ar', 'description_en', 'image', 
        'location_ar', 'location_en', 'rating', 'is_open', 'start_hour', 'close_hour'
    ];

    
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Polymorphic Relationship for Company Reviews
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
    public function workers(): HasMany
    {
        return $this->hasMany(WorkerProfile::class);
    }

    // --- Helper Functions ---

    /**
     * Instantly check if the shop is physically operating right now
     */
    public function isCurrentlyOperating(): bool
    {
        if (!$this->is_open) return false;
        if (!$this->start_hour || !$this->close_hour) return true;

        $now = Carbon::now();
        $start = Carbon::createFromTimeString($this->start_hour);
        $end = Carbon::createFromTimeString($this->close_hour);

        return $now->between($start, $end);
    }

    public function recalculateRating(): void
    {
        $this->update(['rating' => $this->reviews()->avg('rating') ?? 0.00]);
    }
    protected function image(): Attribute
{
    return Attribute::make(
        get: fn ($value) => $value ? asset('storage/' . $value) : null,
    );
}
}
