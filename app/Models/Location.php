<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'name', 'address', 'latitude', 'longitude'];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
