<?php

namespace App\Observers;

use App\Models\Region;
use Illuminate\Support\Facades\Cache;

class RegionObserver
{
    /**
     * Clear relevant caches related to regions and managers.
     * @param  \App\Models\Region|null  $region  
     * @return void
     */
    protected function clearRegionCaches(?Region $region = null)
    {
        Cache::forget('all_regions_with_managers');

        if ($region && $region->manager_id) {
            Cache::forget('regions_for_manager_' . $region->manager_id);
        }

        if ($region) {
            Cache::forget('region_' . $region->id . '_details_with_manager_companies');
        }
        // ------------------------------------------------------------------------------------------
    }

    /**
     * Handle the Region "created" event.
     *
     * @param   $region
     * @return void
     */
    public function created(Region $region)
    {
        $this->clearRegionCaches($region);
    }

    /**
     * Handle the Region "updated" event.
     *
     * @param    $region
     * @return void
     */
    public function updated(Region $region)
    {
        $this->clearRegionCaches($region);

        // إذا تغير manager_id، يجب مسح الكاش الخاص بالمدير القديم أيضًا
        if ($region->isDirty('manager_id')) {
            $originalManagerId = $region->getOriginal('manager_id');
            if ($originalManagerId) {
                Cache::forget('regions_for_manager_' . $originalManagerId);
            }
        }
    }

    /**
     * Handle the Region "deleted" event.
     *
     * @param    $region
     * @return void
     */
    public function deleted(Region $region)
    {
        $this->clearRegionCaches($region);
    }

    // ... (يمكن إضافة المزيد من الأحداث إذا لزم الأمر)
}