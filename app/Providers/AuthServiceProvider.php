<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Company;
use App\Models\Service;
use App\Models\Order;
use App\Models\Task;

use App\Policies\UserPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ServicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\TaskPolicy;


// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Company::class => CompanyPolicy::class,
        Service::class => ServicePolicy::class,
        Order::class => OrderPolicy::class,
        Task::class => TaskPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
         $this->registerPolicies();
    }
}
