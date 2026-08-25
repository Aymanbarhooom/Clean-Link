<?php

namespace Tests\Unit;

use App\Exceptions\Chat\AllGeminiModelsExhaustedException;
use App\Models\User;
use App\Services\Chat\CleanLinkChatToolService;
use App\Services\Chat\GeminiChatService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiChatServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.models' => ['primary-model', 'fallback-model'],
            'services.gemini.base_url' => 'https://gemini.test/v1beta',
            'services.gemini.retry_attempts' => 2,
            'services.gemini.retry_delay_ms' => 0,
        ]);
    }

    public function test_primary_model_succeeds_without_using_fallback(): void
    {
        Http::fakeSequence()->push($this->successResponse('Primary answer'));

        $result = $this->service()->send(null, 'Hello', $this->user());

        $this->assertSame('Primary answer', $result['text']);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) =>
            str_contains($request->url(), '/models/primary-model:generateContent'));
    }

    public function test_final_assistant_text_is_formatted_without_touching_provider_flow(): void
    {
        Http::fakeSequence()->push(
            $this->successResponse("### **Available Services**\n\n- Home Cleaning\n- Office Cleaning")
        );

        $result = $this->service()->send(null, 'Services', $this->user());

        $this->assertSame(
            "Available Services\n\nHome Cleaning\nOffice Cleaning",
            $result['text']
        );
        Http::assertSentCount(1);
    }

    public function test_quota_failure_falls_back_immediately(): void
    {
        Http::fakeSequence()
            ->push($this->quotaResponse('primary-quota', '12s'), 429)
            ->push($this->successResponse('Fallback answer'));

        $result = $this->service()->send(null, 'Hello', $this->user());

        $this->assertSame('Fallback answer', $result['text']);
        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $this->assertStringContainsString('primary-model', $requests[0][0]->url());
        $this->assertStringContainsString('fallback-model', $requests[1][0]->url());
    }

    public function test_all_model_quota_failures_raise_exhausted_exception(): void
    {
        Http::fakeSequence()
            ->push($this->quotaResponse('primary-quota', '10s'), 429)
            ->push($this->quotaResponse('fallback-quota', '20s'), 429);

        $this->expectException(AllGeminiModelsExhaustedException::class);

        $this->service()->send(null, 'Hello', $this->user());
    }

    public function test_temporary_failure_retries_twice_then_uses_fallback(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['status' => 'UNAVAILABLE']], 503)
            ->push(['error' => ['status' => 'UNAVAILABLE']], 503)
            ->push($this->successResponse('Recovered answer'));

        $result = $this->service()->send(null, 'Hello', $this->user());

        $this->assertSame('Recovered answer', $result['text']);
        $requests = Http::recorded();
        $this->assertCount(3, $requests);
        $this->assertStringContainsString('primary-model', $requests[0][0]->url());
        $this->assertStringContainsString('primary-model', $requests[1][0]->url());
        $this->assertStringContainsString('fallback-model', $requests[2][0]->url());
    }

    public function test_fallback_preserves_function_call_state_and_id(): void
    {
        $candidate = [
            'role' => 'model',
            'parts' => [[
                'thoughtSignature' => 'signed-provider-state',
                'functionCall' => [
                    'id' => 'call-123',
                    'name' => 'search_services',
                    'args' => ['query' => 'cleaning'],
                ],
            ]],
        ];

        Http::fakeSequence()
            ->push($this->quotaResponse('primary-quota', '10s'), 429)
            ->push(['candidates' => [['content' => $candidate]]])
            ->push($this->successResponse('Tool answer'));

        $toolService = $this->createMock(CleanLinkChatToolService::class);
        $toolService->expects($this->once())
            ->method('execute')
            ->with('search_services', ['query' => 'cleaning'], $this->isInstanceOf(User::class))
            ->willReturn(['items' => []]);

        $result = (new GeminiChatService($toolService))
            ->send(null, 'Find cleaning', $this->user());

        $this->assertSame('Tool answer', $result['text']);
        $requests = Http::recorded();
        $followUpContents = $requests[2][0]->data()['contents'];
        $this->assertSame($candidate, $followUpContents[1]);
        $this->assertSame(
            'call-123',
            $followUpContents[2]['parts'][0]['functionResponse']['id']
        );
    }

    public function test_empty_function_arguments_remain_a_json_object(): void
    {
        Http::fakeSequence()
            ->push([
                'candidates' => [[
                    'content' => [
                        'role' => 'model',
                        'parts' => [[
                            'thoughtSignature' => 'signed-empty-arguments',
                            'functionCall' => [
                                'id' => 'call-empty',
                                'name' => 'get_my_locations',
                                'args' => [],
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push($this->successResponse('No saved locations'));

        $toolService = $this->createMock(CleanLinkChatToolService::class);
        $toolService->expects($this->once())
            ->method('execute')
            ->with('get_my_locations', [], $this->isInstanceOf(User::class))
            ->willReturn(['count' => 0, 'locations' => []]);

        (new GeminiChatService($toolService))
            ->send(null, 'Show my locations', $this->user());

        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $this->assertStringContainsString(
            '"args":{}',
            $requests[1][0]->body()
        );
    }

    private function service(): GeminiChatService
    {
        return new GeminiChatService(
            $this->createStub(CleanLinkChatToolService::class)
        );
    }

    private function user(): User
    {
        $user = new User();
        $user->id = 99;

        return $user;
    }

    private function successResponse(string $text): array
    {
        return [
            'responseId' => 'response-1',
            'candidates' => [[
                'content' => [
                    'role' => 'model',
                    'parts' => [['text' => $text]],
                ],
            ]],
        ];
    }

    private function quotaResponse(string $quotaId, string $retryDelay): array
    {
        return [
            'error' => [
                'status' => 'RESOURCE_EXHAUSTED',
                'details' => [[
                    'quotaId' => $quotaId,
                    'retryDelay' => $retryDelay,
                ]],
            ],
        ];
    }
}
