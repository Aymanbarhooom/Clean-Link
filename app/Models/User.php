<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = ['fullname', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];

    // --- Relationships ---

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function workerProfile(): HasOne
    {
        return $this->hasOne(WorkerProfile::class);
    }

    public function managedRegions(): HasMany
    {
        return $this->hasMany(Region::class, 'manager_id');
    }

    public function managedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'manager_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'worker_id');
    }

    // --- Helper Functions ---

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isWorker(): bool
    {
        return $this->role === 'worker';
    }

    public function isCompanyManager(): bool
    {
        return $this->role === 'company_manager';
    }
    public function fcmTokens():  HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

}
