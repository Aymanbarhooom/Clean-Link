<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryObserver
{
    protected function clearHomepageCategoryCaches()
    {
        Cache::forget('homepage_categories');
    }
    /**
     * Handle the Category "created" event.
     *
     * @param  $category
     * @return void
     */
    public function created(Category $category)
    {
        Cache::forget('all_categories');
        $this->clearHomepageCategoryCaches();
    }

    /**
     * Handle the Category "updated" event.
     *
     * @param  $category
     * @return void
     */
    public function updated(Category $category)
    {
        Cache::forget('all_categories');
        Cache::forget('category_' . $category->id . '_with_services_images');
        $this->clearHomepageCategoryCaches();
    }

    /**
     * Handle the Category "deleted" event.
     *
     * @param  $category
     * @return void
     */
    public function deleted(Category $category)
    {
        Cache::forget('all_categories');
        Cache::forget('category_' . $category->id . '_with_services_images');
        $this->clearHomepageCategoryCaches();
    }

    // يمكنك إضافة events أخرى مثل restoring, forceDeleted إذا لزم الأمر
}