<?php

namespace App\Observers;

use App\Models\Region;
use Illuminate\Support\Facades\Cache;

class RegionObserver
{
    /**
     * Clear all region-related caches.
     * 
     * @param  \App\Models\Region|null  $region
     * @return void
     */
    protected function clearRegionCaches(?Region $region = null)
    {
        // 1. مسح الكاش الرئيسي (غير المقسم)
        Cache::forget('all_regions_with_managers');
        Cache::forget('all_regions_with_managers_all');

        // 2. مسح جميع مفاتيح الكاش المقسمة (pagination)
        $this->clearAllPaginatedCache();

        // 3. مسح كاش المدير الحالي
        if ($region && $region->manager_id) {
            Cache::forget('regions_for_manager_' . $region->manager_id);
            $this->clearPaginatedCacheForManager($region->manager_id);
        }

        // 4. مسح كاش تفاصيل المنطقة المحددة
        if ($region) {
            Cache::forget('region_' . $region->id . '_details_with_manager_companies');
        }
    }

    /**
     * Clear all paginated cache keys for regions.
     *
     * @return void
     */
    protected function clearAllPaginatedCache(): void
    {
        // مسح جميع مفاتيح الـ pagination الخاصة بالمناطق
        $keys = [
            'all_regions_with_managers_paginated_',
            'regions_for_manager_',
        ];

        foreach ($keys as $prefix) {
            $this->clearCacheByPrefix($prefix);
        }
    }

    /**
     * Clear paginated cache for a specific manager.
     *
     * @param  int  $managerId
     * @return void
     */
    protected function clearPaginatedCacheForManager(int $managerId): void
    {
        $prefix = 'regions_for_manager_' . $managerId . '_paginated_';
        $this->clearCacheByPrefix($prefix);
    }

    /**
     * Clear cache keys by prefix using Redis pattern matching.
     * إذا كنت تستخدم Redis، هذه الطريقة مثالية.
     * 
     * @param  string  $prefix
     * @return void
     */
    protected function clearCacheByPrefix(string $prefix): void
    {
        // الطريقة المثالية: باستخدام Redis
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $redis = Cache::getStore()->connection();
            $keys = $redis->keys($prefix . '*');
            
            if (!empty($keys)) {
                $redis->del($keys);
            }
            return;
        }

        // الطريقة البديلة: باستخدام Cache::tags (إذا كنت تستخدم tags)
        if (method_exists(Cache::getStore(), 'tags')) {
            // استخدم Cache::tags إذا كان مدعوماً
            $tag = str_replace('_paginated_', '', $prefix);
            Cache::tags([$tag])->flush();
            return;
        }

        // إذا كنت تستخدم File أو Database، الحل الأمثل هو استخدام مفتاح واحد
        // وعدم استخدام pagination في الكاش أصلاً
        // لاحظ أن هذه الطريقة لن تمسح جميع المفاتيح في File/Database
        // ولكننا نستخدم Cache::forget للمفاتيح المعروفة
    }

    /**
     * Handle the Region "created" event.
     *
     * @param  Region  $region
     * @return void
     */
    public function created(Region $region): void
    {
        $this->clearRegionCaches($region);
    }

    /**
     * Handle the Region "updated" event.
     *
     * @param  Region  $region
     * @return void
     */
    public function updated(Region $region): void
    {
        $originalManagerId = $region->getOriginal('manager_id');
        $newManagerId = $region->manager_id;

        // مسح الكاش العام
        $this->clearRegionCaches($region);

        // إذا تغير manager_id، مسح كاش المدير القديم أيضاً
        if ($region->isDirty('manager_id') && $originalManagerId && $originalManagerId !== $newManagerId) {
            Cache::forget('regions_for_manager_' . $originalManagerId);
            $this->clearPaginatedCacheForManager($originalManagerId);
        }
    }

    /**
     * Handle the Region "deleted" event.
     *
     * @param  Region  $region
     * @return void
     */
    public function deleted(Region $region): void
    {
        $this->clearRegionCaches($region);
    }

    /**
     * Handle the Region "restored" event (إذا كنت تستخدم SoftDeletes).
     *
     * @param  Region  $region
     * @return void
     */
    public function restored(Region $region): void
    {
        $this->clearRegionCaches($region);
    }

    /**
     * Handle the Region "forceDeleted" event (إذا كنت تستخدم SoftDeletes).
     *
     * @param  Region  $region
     * @return void
     */
    public function forceDeleted(Region $region): void
    {
        $this->clearRegionCaches($region);
    }
}