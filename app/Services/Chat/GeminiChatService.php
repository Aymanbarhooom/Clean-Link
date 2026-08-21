<?php


namespace App\Services\Chat;


use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;


class GeminiChatService
{
    public function __construct(
        private readonly CleanLinkChatToolService $toolService
    ) {
    }


    public function send(
        ?ChatConversation $conversation,
        string $newMessage,
        User $user
    ): array {
        $contents = $this->buildContents(
            $conversation,
            $newMessage
        );


        $lastResponseId = null;


        for ($iteration = 0; $iteration < 8; $iteration++) {
            $response = $this->request($contents);


            $lastResponseId =
                $response['responseId']
                ?? $lastResponseId;


            $candidateContent =
                $response['candidates'][0]['content']
                ?? null;


            if (!$candidateContent) {
                throw new RuntimeException(
                    'Gemini returned no content.'
                );
            }


            $functionCalls =
                $this->extractFunctionCalls(
                    $candidateContent
                );


            if (empty($functionCalls)) {
                $text = $this->extractText(
                    $candidateContent
                );


                if ($text === '') {
                    throw new RuntimeException(
                        'Gemini returned an empty answer.'
                    );
                }


                return [
                    'text' => $text,
                    'response_id' => $lastResponseId,
                ];
            }


            $contents[] = $candidateContent;


            $functionResponseParts = [];


            foreach ($functionCalls as $functionCall) {
                $name =
                    $functionCall['name']
                    ?? '';


                $arguments =
                    $functionCall['args']
                    ?? [];


                $id =
                    $functionCall['id']
                    ?? null;


                $result =
                    $this->toolService
                        ->execute(
                            $name,
                            is_array($arguments)
                                ? $arguments
                                : [],
                            $user
                        );


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
                    'functionResponse' =>
                        $functionResponse,
                ];
            }


            $contents[] = [
                'role' => 'user',
                'parts' =>
                    $functionResponseParts,
            ];
        }


        throw new RuntimeException(
            'Gemini exceeded the maximum tool-call iterations.'
        );
    }


    private function request(
        array $contents
    ): array {
        $model = config(
            'services.gemini.model'
        );


        $baseUrl = rtrim(
            config(
                'services.gemini.base_url'
            ),
            '/'
        );


        $url =
            $baseUrl .
            '/models/' .
            $model .
            ':generateContent';


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
            throw new RuntimeException(
                'Gemini request failed: ' .
                $response->body()
            );
        }


        return $response->json();
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


        foreach (
            $candidateContent['parts'] ?? []
            as $part
        ) {
            if (
                isset($part['functionCall']) &&
                is_array($part['functionCall'])
            ) {
                $calls[] =
                    $part['functionCall'];
            }
        }


        return $calls;
    }


    private function extractText(
        array $candidateContent
    ): string {
        $text = '';


        foreach (
            $candidateContent['parts'] ?? []
            as $part
        ) {
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
