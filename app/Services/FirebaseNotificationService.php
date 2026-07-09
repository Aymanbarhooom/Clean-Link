<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected $projectId;
    protected $credentialsPath;

    public function __construct()
    {
        $this->projectId = env('FCM_PROJECT_ID');
        // استخدام storage_path لضمان الوصول للملف بشكل صحيح
        $this->credentialsPath = storage_path('app/firebase-credentials.json');
    }

    /**
     * الحصول على Access Token مؤقت من جوجل
     */
    private function getAccessToken()
    {
        $client = new GoogleClient();
        $client->setAuthConfig($this->credentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->fetchAccessTokenWithAssertion();

        return $client->getAccessToken()['access_token'];
    }

    /**
     * إرسال إشعار لجهاز محدد باستخدام الـ Token
     */
    public function sendPushNotification($fcmToken, $title, $body, $data = [])
    {
        try {
            $accessToken = $this->getAccessToken();

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],

                    'data' => array_map('strval', $data),

                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'high_importance_channel',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ];

            Log::info('FCM Payload', $payload);

            $response = Http::withToken($accessToken)
                ->post($url, $payload);

            Log::info('FCM Response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->json(),
                'raw' => $response->body(),
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Firebase Notification Error: ' . $e->getMessage());
            return false;
        }
    }
}