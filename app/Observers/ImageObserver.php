<?php

namespace App\Observers;

use App\Models\Image; // افترض أن اسم نموذج الصورة هو Image
use App\Models\ServiceImage;
use Illuminate\Support\Facades\Cache;

class ImageObserver
{
    /**
     * Handle the Image "created" event.
     *
     * @param   $image
     * @return void
     */
    public function created(ServiceImage $image)
    {
        // تحتاج للوصول إلى Service ومن ثم Category_id
        $service = $image->service; // افترض أن Image لديها علاقة belongsTo مع Service
        if ($service) {
            Cache::forget('category_' . $service->category_id . '_with_services_images');
        }
    }

    /**
     * Handle the Image "updated" event.
     *
     * @param    $image
     * @return void
     */
    public function updated(ServiceImage $image)
    {
        $service = $image->service;
        if ($service) {
            Cache::forget('category_' . $service->category_id . '_with_services_images');
        }
    }

    /**
     * Handle the Image "deleted" event.
     *
     * @param    $image
     * @return void
     */
    public function deleted(ServiceImage $image)
    {
        $service = $image->service;
        if ($service) {
            Cache::forget('category_' . $service->category_id . '_with_services_images');
        }
    }
}