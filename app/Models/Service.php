<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Service extends Model
{
    protected $fillable = [
        'company_id',
        'category_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'rating',
        'min_duration',
        'max_duration',
        'price',
        'image',
        'discount'
    ];
    protected $appends = ['is_favorite'];

    // --- Relationships ---

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(AttributeModel::class, 'attribute_service', 'service_id', 'attribute_id')
            ->withPivot('price', 'duration')
            ->withTimestamps();
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
    public function images()
    {
        return $this->hasMany(ServiceImage::class);
    }
    public function requiredSkills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'service_skills')->withTimestamps();
    }
    public function favoritedBy(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }



    // --- Helper Functions ---

    public function getFinalPriceAttribute(): float
    {
        return max(0, $this->price - $this->discount);
    }

    public function recalculateRating(): void
    {
        $this->update(['rating' => $this->reviews()->avg('rating') ?? 0.00]);
    }
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? asset('storage/' . $value) : null,
        );
    }

    public function getIsFavoriteAttribute(): bool
{
    if (!auth()->check()) {
        return false;
    }

    return $this->favoritedBy()
                ->where('user_id', auth()->id())
                ->exists();
}
}
