<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    protected $fillable = ['name_ar', 'name_en'];

    public function workerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(WorkerProfile::class, 'skill_worker_profiles');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_skills');
    }
}
