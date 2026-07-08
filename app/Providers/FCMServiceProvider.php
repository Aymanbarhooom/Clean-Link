<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Google\Client;
use Google\Service\FirebaseCloudMessaging;

class FCMServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FirebaseCloudMessaging::class, function ($app) {
            $client = new Client();
            $client->setAuthConfig(storage_path('app/firebase-credentials.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            
            return new FirebaseCloudMessaging($client);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
