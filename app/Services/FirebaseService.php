<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected string $projectId;
    protected string $credentialsPath;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id');
        $this->credentialsPath = storage_path('app/' . basename(config('services.fcm.credentials_path')));
    }

    protected function getAccessToken(): string
    {
        $client = new GoogleClient();
        $client->setAuthConfig($this->credentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $token = $client->fetchAccessTokenWithAssertion();

        return $token['access_token'];
    }

    /**
     * إرسال إشعار لتوكن واحد
     */
    public function sendToToken(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        try {
            $accessToken = $this->getAccessToken();

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            $message = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', $data), // FCM بده كل الـ data قيم نصية
                ],
            ];

            $response = Http::withToken($accessToken)
                ->post($url, $message);

            if ($response->failed()) {
                Log::error('FCM send failed', [
                    'token' => $fcmToken,
                    'response' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('FCM exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إرسال إشعار لكل توكنات مستخدم معيّن
     */
    public function sendToUser($user, string $title, string $body, array $data = []): void
    {
        foreach ($user->fcmTokens as $fcmToken) {
            $this->sendToToken($fcmToken->token, $title, $body, $data);
        }
    }
}