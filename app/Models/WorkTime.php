<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTime extends Model
{
    use HasFactory;
    protected $fillable = ['company_id', 'day_of_week', 'open_at', 'close_at', 'is_holiday'];

    public function company(): BelongsTo {
        return $this->belongsTo(Company::class);
    }
}
