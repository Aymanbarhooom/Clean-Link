<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkerProfile extends Model
{
    protected $fillable = ['user_id', 'company_id', 'experience_years', 'rating'];
    protected $casts = [
        'experience_years' => 'integer',
        'rating' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'skill_worker_profiles')->withTimestamps();
    }

}
