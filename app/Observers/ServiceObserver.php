<?php

namespace App\Observers;

use App\Models\Service;
use Illuminate\Support\Facades\Cache;

class ServiceObserver
{

    protected function clearHomepageServiceCaches()
    {
        Cache::forget('homepage_offers');
        Cache::forget('homepage_top_services');
        $this->clearHomepageServiceCaches();
    }
    /**
     * Handle the Service "created" event.
     *
     * @param   $service
     * @return void
     */
    public function created(Service $service)
    {
        Cache::forget('all_categories'); // ربما يؤثر على قائمة الفئات العامة إذا كانت تعرض أعداد الخدمات
        Cache::forget('category_' . $service->category_id . '_with_services_images');
        $this->clearHomepageServiceCaches();
    }

    /**
     * Handle the Service "updated" event.
     *
     * @param   $service
     * @return void
     */
    public function updated(Service $service)
    {
        Cache::forget('all_categories');
        Cache::forget('category_' . $service->category_id . '_with_services_images');
        $this->clearHomepageServiceCaches();
    }

    /**
     * Handle the Service "deleted" event.
     *
     * @param   $service
     * @return void
     */
    public function deleted(Service $service)
    {
        Cache::forget('all_categories');
        Cache::forget('category_' . $service->category_id . '_with_services_images');
        $this->clearHomepageServiceCaches();
    }
}