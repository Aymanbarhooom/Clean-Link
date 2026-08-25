<?php

namespace Tests\Unit;

use App\Exceptions\Chat\AllGeminiModelsExhaustedException;
use App\Exceptions\Chat\GeminiConnectionException;
use App\Exceptions\Chat\GeminiTemporaryUnavailableException;
use App\Http\Controllers\ChatController;
use App\Http\Requests\SendChatMessageRequest;
use App\Models\User;
use App\Services\Chat\GeminiChatService;
use Tests\TestCase;

class ChatControllerErrorTest extends TestCase
{
    /** @dataProvider chatFailureProvider */
    public function test_chat_failures_have_stable_http_statuses_and_codes(
        \Throwable $exception,
        int $expectedStatus,
        string $expectedCode
    ): void {
        $service = $this->createMock(GeminiChatService::class);
        $service->method('send')->willThrowException($exception);

        $request = SendChatMessageRequest::create(
            '/api/chat/messages',
            'POST',
            ['message' => 'Hello']
        );
        $user = new User();
        $user->id = 42;
        $request->setUserResolver(static fn () => $user);

        $response = (new ChatController($service))->send($request);
        $payload = $response->getData(true);

        $this->assertSame($expectedStatus, $response->getStatusCode());
        $this->assertSame($expectedCode, $payload['code']);
    }

    public static function chatFailureProvider(): array
    {
        return [
            'all quotas exhausted' => [
                new AllGeminiModelsExhaustedException(),
                429,
                'CHAT_QUOTA_EXCEEDED',
            ],
            'temporary provider failure' => [
                new GeminiTemporaryUnavailableException('model', 503),
                503,
                'CHAT_TEMPORARILY_UNAVAILABLE',
            ],
            'provider connection failure' => [
                new GeminiConnectionException('model'),
                503,
                'CHAT_CONNECTION_ERROR',
            ],
        ];
    }
}
