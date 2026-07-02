<?php

// app/Models/Workgroup.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workgroup extends Model
{
    protected $fillable = ['company_id', 'name', 'leader_id'];


    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_workgroup', 'workgroup_id', 'user_id')
                    ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    
    public function getCombinedSkillIds(): array
    {
        // Load nested worker profile skill IDs cleanly
        return $this->workers()
            ->with('workerProfile.skills')
            ->get()
            ->pluck('workerProfile.skills')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->toArray();
    }
}

