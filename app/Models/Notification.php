<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = ['user_id', 'title_ar', 'title_en', 'body_ar', 'body_en', 'is_read'];

    protected $casts = [
        'is_read' => 'boolean',
        'data' => 'array', 
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
