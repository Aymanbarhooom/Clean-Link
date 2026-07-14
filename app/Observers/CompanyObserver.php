<?php

namespace App\Observers;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;

class CompanyObserver
{
    protected function clearHomepageCompanyCaches()
    {
        Cache::forget('homepage_top_companies');
    }

    public function created(Company $company)
    {
        $this->clearHomepageCompanyCaches();
    }

    public function updated(Company $company)
    {
        if ($company->isDirty('rating')) {
            $this->clearHomepageCompanyCaches();
        }
    }

    public function deleted(Company $company)
    {
        $this->clearHomepageCompanyCaches();
    }
}