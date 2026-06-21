<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Profile extends Model
{
    protected $fillable = ['user_id', 'image', 'address', 'phone'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/' . $value) : null,
        );
    }
}
