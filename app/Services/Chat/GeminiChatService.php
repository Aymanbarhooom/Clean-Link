<?php

namespace App\Services\Chat;

use App\Exceptions\Chat\AllGeminiModelsExhaustedException;
use App\Exceptions\Chat\GeminiConnectionException;
use App\Exceptions\Chat\GeminiQuotaExceededException;
use App\Exceptions\Chat\GeminiRequestException;
use App\Exceptions\Chat\GeminiTemporaryUnavailableException;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiChatService
{
    private const MAX_TOOL_ITERATIONS = 8;

    private readonly ChatResponseFormatter $responseFormatter;

    public function __construct(
        private readonly CleanLinkChatToolService $toolService,
        ?ChatResponseFormatter $responseFormatter = null,
    ) {
        $this->responseFormatter = $responseFormatter ?? new ChatResponseFormatter();
    }

    public function send(
        ?ChatConversation $conversation,
        string $newMessage,
        User $user
    ): array {
        $models = $this->configuredModels();
        $quotaFailures = 0;
        $lastTemporaryFailure = null;
        $lastRequestFailure = null;

        foreach ($models as $index => $model) {
            Log::info('Gemini chat model attempt', [
                'model' => $model,
                'conversation_id' => $conversation?->id,
                'user_id' => $user->id,
            ]);

            try {
                $result = $this->sendUsingModel(
                    $model,
                    $conversation,
                    $newMessage,
                    $user
                );

                Log::info('Gemini chat succeeded', [
                    'model' => $model,
                    'conversation_id' => $conversation?->id,
                    'user_id' => $user->id,
                ]);

                return $result;
            } catch (GeminiQuotaExceededException $exception) {
                $quotaFailures++;
                Log::warning('Gemini model quota exhausted', [
                    'model' => $model,
                    'status' => $exception->status,
                    'quota_id' => $exception->quotaId,
                    'retry_delay' => $exception->retryDelay,
                    'fallback_model' => $models[$index + 1] ?? null,
                    'conversation_id' => $conversation?->id,
                    'user_id' => $user->id,
                ]);
            } catch (GeminiTemporaryUnavailableException $exception) {
                $lastTemporaryFailure = $exception;

                Log::warning('Gemini model temporarily unavailable', [
                    'model' => $model,
                    'status' => $exception->status,
                    'fallback_model' => $models[$index + 1] ?? null,
                    'conversation_id' => $conversation?->id,
                    'user_id' => $user->id,
                ]);
            } catch (GeminiRequestException $exception) {
                if ($exception->status !== 404) {
                    throw $exception;
                }

                $lastRequestFailure = $exception;

                Log::warning('Gemini model unavailable', [
                    'model' => $model,
                    'status' => $exception->status,
                    'fallback_model' => $models[$index + 1] ?? null,
                    'conversation_id' => $conversation?->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        if ($quotaFailures === count($models)) {
            throw new AllGeminiModelsExhaustedException();
        }

        if ($lastTemporaryFailure instanceof GeminiTemporaryUnavailableException) {
            throw $lastTemporaryFailure;
        }

        if ($lastRequestFailure instanceof GeminiRequestException) {
            throw $lastRequestFailure;
        }

        throw new AllGeminiModelsExhaustedException();
    }

    private function sendUsingModel(
        string $model,
        ?ChatConversation $conversation,
        string $newMessage,
        User $user
    ): array {
        $contents = $this->buildContents(
            $conversation,
            $newMessage
        );
        $lastResponseId = null;
        $lastAction = null;

        for ($iteration = 0; $iteration < self::MAX_TOOL_ITERATIONS; $iteration++) {
            $response = $this->requestWithRetry(
                $model,
                $contents
            );

            $lastResponseId =
                $response['responseId']
                ?? $lastResponseId;

            $candidateContent =
                $response['candidates'][0]['content']
                ?? null;

            if (!is_array($candidateContent)) {
                throw new GeminiTemporaryUnavailableException(
                    $model,
                    502,
                    'Gemini returned no content.'
                );
            }

            $functionCalls = $this->extractFunctionCalls(
                $candidateContent
            );

            if (empty($functionCalls)) {
                $text = $this->responseFormatter->format(
                    $this->extractText($candidateContent)
                );

                if ($text === '') {
                    throw new GeminiTemporaryUnavailableException(
                        $model,
                        502,
                        'Gemini returned an empty answer.'
                    );
                }

                return [
                    'text' => $text,
                    'response_id' => $lastResponseId,
                    'action' => $lastAction,
                ];
            }

            // Preserve the complete candidate content, including function-call
            // IDs, thought signatures, and any provider metadata.
            $contents[] = $this->candidateContentForHistory(
                $candidateContent
            );
            $functionResponseParts = [];


            foreach ($functionCalls as $functionCall) {
                $name = $functionCall['name'] ?? '';
                $arguments = $functionCall['args'] ?? [];
                $id = $functionCall['id'] ?? null;

                $result = $conversation
                    ? $this->toolService->execute(
                        $name,
                        is_array($arguments) ? $arguments : [],
                        $user,
                        $conversation,
                        $newMessage
                    )
                    : $this->toolService->execute(
                        $name,
                        is_array($arguments) ? $arguments : [],
                        $user
                    );

                if (isset($result['_action']) && is_array($result['_action'])) {
                    $lastAction = $result['_action'];
                }

                $functionResponse = [
                    'name' => $name,
                    'response' => [
                        'result' => $result,
                    ],
                ];


                if ($id) {
                    $functionResponse['id'] = $id;
                }


                $functionResponseParts[] = [
                    'functionResponse' => $functionResponse,
                ];
            }


            $contents[] = [
                'role' => 'user',
                'parts' => $functionResponseParts,
            ];
        }

        throw new GeminiTemporaryUnavailableException(
            $model,
            502,
            'Gemini exceeded the maximum tool-call iterations.'
        );
    }

    private function requestWithRetry(
        string $model,
        array $contents
    ): array {
        $maxAttempts = max(
            1,
            (int) config('services.gemini.retry_attempts', 2)
        );
        $baseDelayMs = max(
            0,
            (int) config('services.gemini.retry_delay_ms', 250)
        );

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->request(
                    $model,
                    $contents
                );
            } catch (GeminiQuotaExceededException $exception) {
                throw $exception;
            } catch (GeminiTemporaryUnavailableException $exception) {
                if ($attempt === $maxAttempts) {
                    throw $exception;
                }

                $this->logRetry(
                    $model,
                    $attempt,
                    $exception->status,
                    $baseDelayMs
                );
                $this->delay($baseDelayMs, $attempt);
            } catch (ConnectionException $exception) {
                if ($attempt === $maxAttempts) {
                    throw new GeminiConnectionException(
                        $model,
                        $exception
                    );
                }

                $this->logRetry(
                    $model,
                    $attempt,
                    null,
                    $baseDelayMs
                );
                $this->delay($baseDelayMs, $attempt);
            }
        }

        throw new GeminiConnectionException($model);
    }

    private function request(
        string $model,
        array $contents
    ): array {
        $baseUrl = rtrim(
            (string) config('services.gemini.base_url'),
            '/'
        );
        $url = $baseUrl . '/models/' . $model . ':generateContent';

        $response = Http::withHeaders([
            'x-goog-api-key' =>
                config('services.gemini.key'),
        ])
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->post(
                $url,
                [
                    'systemInstruction' => [
                        'parts' => [
                            [
                                'text' =>
                                    CleanLinkChatInstructions::text(),
                            ],
                        ],
                    ],
                    'contents' => $contents,
                    'tools' =>
                        CleanLinkChatTools::definitions(),
                    'toolConfig' => [
                        'functionCallingConfig' => [
                            'mode' => 'AUTO',
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 1000,
                    ],
                ]
            );

        if (!$response->successful()) {
            $this->throwForResponse(
                $model,
                $response
            );
        }

        $json = $response->json();

        if (!is_array($json)) {
            throw new GeminiTemporaryUnavailableException(
                $model,
                502,
                'Gemini returned an invalid response.'
            );
        }

        return $json;
    }

    private function throwForResponse(
        string $model,
        Response $response
    ): never {
        $status = $response->status();
        $error = $response->json('error');
        $error = is_array($error) ? $error : [];
        $providerStatus = $error['status'] ?? null;

        if ($status >= 400 && $status <= 499 && $status !== 429) {
            Log::warning('Gemini request rejected', [
                'model' => $model,
                'status' => $status,
                'provider_status' => $providerStatus,
                'provider_message' => mb_substr(
                    (string) ($error['message'] ?? ''),
                    0,
                    500
                ),
            ]);
        }

        if (
            $status === 429 ||
            $providerStatus === 'RESOURCE_EXHAUSTED'
        ) {
            $metadata = $this->quotaMetadata($error);

            throw new GeminiQuotaExceededException(
                $model,
                $status,
                $metadata['quota_id'],
                $metadata['retry_delay']
            );
        }

        if ($status >= 500 && $status <= 599) {
            throw new GeminiTemporaryUnavailableException(
                $model,
                $status
            );
        }

        throw new GeminiRequestException(
            $model,
            $status
        );
    }

    private function configuredModels(): array
    {
        $models = config('services.gemini.models', []);
        $models = is_array($models) ? $models : [];
        $models = array_values(array_unique(array_filter(
            array_map(
                static fn ($model) => trim((string) $model),
                $models
            )
        )));

        if (empty($models)) {
            throw new GeminiRequestException(
                'unconfigured',
                500,
                'No Gemini models are configured.'
            );
        }

        if (!filled(config('services.gemini.key'))) {
            throw new GeminiRequestException(
                $models[0],
                500,
                'The Gemini API key is not configured.'
            );
        }

        return $models;
    }

    private function quotaMetadata(array $error): array
    {
        $metadata = [
            'quota_id' => null,
            'retry_delay' => null,
        ];

        $walk = function ($value) use (&$walk, &$metadata): void {
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $key => $child) {
                if (
                    $metadata['quota_id'] === null &&
                    in_array($key, ['quotaId', 'quota_id'], true) &&
                    is_scalar($child)
                ) {
                    $metadata['quota_id'] = (string) $child;
                }

                if (
                    $metadata['retry_delay'] === null &&
                    in_array($key, ['retryDelay', 'retry_delay'], true) &&
                    is_scalar($child)
                ) {
                    $metadata['retry_delay'] = (string) $child;
                }

                $walk($child);
            }
        };

        $walk($error['details'] ?? []);

        return $metadata;
    }

    private function logRetry(
        string $model,
        int $attempt,
        ?int $status,
        int $baseDelayMs
    ): void {
        Log::warning('Retrying temporary Gemini failure', [
            'model' => $model,
            'attempt' => $attempt,
            'status' => $status,
            'retry_delay_ms' =>
                $baseDelayMs * (2 ** ($attempt - 1)),
        ]);
    }

    private function delay(
        int $baseDelayMs,
        int $attempt
    ): void {
        if ($baseDelayMs === 0) {
            return;
        }

        usleep(
            $baseDelayMs *
            (2 ** ($attempt - 1)) *
            1000
        );
    }

    private function buildContents(
        ?ChatConversation $conversation,
        string $newMessage
    ): array {
        $contents = [];


        if ($conversation) {
            $messages = $conversation
                ->messages()
                ->latest()
                ->take(20)
                ->get()
                ->reverse()
                ->values();


            foreach ($messages as $message) {
                $contents[] = [
                    'role' =>
                        $message->role === 'assistant'
                            ? 'model'
                            : 'user',
                    'parts' => [
                        [
                            'text' =>
                                $message->content,
                        ],
                    ],
                ];
            }
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [
                [
                    'text' => $newMessage,
                ],
            ],
        ];

        return $contents;
    }

    private function extractFunctionCalls(
        array $candidateContent
    ): array {
        $calls = [];

        foreach ($candidateContent['parts'] ?? [] as $part) {
            if (
                isset($part['functionCall']) &&
                is_array($part['functionCall'])
            ) {
                $calls[] = $part['functionCall'];
            }
        }

        return $calls;
    }

    /**
     * Laravel decodes an empty JSON object as an empty PHP array. Gemini's
     * functionCall.args field is an object, so restore {} before encoding the
     * candidate into the follow-up request. All other provider state remains
     * byte-for-byte equivalent at the JSON value level.
     */
    private function candidateContentForHistory(
        array $candidateContent
    ): array {
        foreach ($candidateContent['parts'] ?? [] as $index => $part) {
            if (
                isset($part['functionCall']) &&
                is_array($part['functionCall']) &&
                ($part['functionCall']['args'] ?? null) === []
            ) {
                $candidateContent['parts'][$index]
                    ['functionCall']['args'] = new \stdClass();
            }
        }

        return $candidateContent;
    }

    private function extractText(
        array $candidateContent
    ): string {
        $text = '';

        foreach ($candidateContent['parts'] ?? [] as $part) {
            if (
                isset($part['text']) &&
                is_string($part['text'])
            ) {
                $text .= $part['text'];
            }
        }


        return trim($text);
    }
}
