<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Region;
use App\Models\Service;
use App\Models\ServiceImage;
use App\Observers\CategoryObserver;
use App\Observers\CompanyObserver;
use App\Observers\ImageObserver;
use App\Observers\RegionObserver;
use App\Observers\ServiceObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        Category::observe(CategoryObserver::class);
        Service::observe(ServiceObserver::class);
        ServiceImage::observe(ImageObserver::class);
        Region::observe(RegionObserver::class);
        Company::observe(CompanyObserver::class);
    }
}
