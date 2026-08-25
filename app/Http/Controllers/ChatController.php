<?php


namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Exceptions\Chat\AllGeminiModelsExhaustedException;
use App\Exceptions\Chat\GeminiConnectionException;
use App\Exceptions\Chat\GeminiRequestException;
use App\Exceptions\Chat\GeminiTemporaryUnavailableException;
use App\Http\Requests\SendChatMessageRequest;
use App\Models\ChatConversation;
use App\Services\Chat\GeminiChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;


class ChatController extends Controller
{
    public function __construct(
        private readonly GeminiChatService $chatService
    ) {}


    public function index(
        Request $request
    ) {
        $conversations =
            ChatConversation::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->withCount('messages')
            ->latest('updated_at')
            ->get();


        return response()->json([
            'data' => $conversations,
        ]);
    }


    public function show(
        Request $request,
        ChatConversation $conversation
    ) {
        $this->ensureOwner(
            $request,
            $conversation
        );


        $conversation->load([
            'messages' => function ($query) {
                $query->oldest();
            },
        ]);


        return response()->json([
            'data' => $conversation,
        ]);
    }


    public function send(SendChatMessageRequest $request)
    {
        $user = $request->user();
        $conversation = null;
        if ($request->filled('conversation_id')) {
            $conversation = ChatConversation::query()->where(
                'id',
                $request->integer('conversation_id')
            )
                ->where(
                    'user_id',
                    $user->id
                )
                ->firstOrFail();
        }

        if (!$conversation) {
            $conversation = ChatConversation::create([
                'user_id' => $user->id,
                'title' => $this->createTitle($request->string('message')->toString()),
            ]);
        }

        try {
            $result =
                $this->chatService
                ->send(
                    $conversation,
                    $request->string(
                        'message'
                    )->toString(),
                    $user
                );


            return DB::transaction(
                function () use (
                    $conversation,
                    $request,
                    $user,
                    $result
                ) {
                    $userMessage =
                        $conversation
                        ->messages()
                        ->create([
                            'role' => 'user',
                            'content' =>
                            $request->string(
                                'message'
                            )->toString(),
                        ]);


                    $assistantMessage =
                        $conversation
                        ->messages()
                        ->create([
                            'role' => 'assistant',
                            'content' =>
                            $result['text'],
                            'action' =>
                            $result['action'] ?? null,
                            'gemini_response_id' =>
                            $result['response_id'] ?? null,
                        ]);


                    $conversation->touch();


                    return response()->json([
                        'data' => [
                            'conversation_id' =>
                            $conversation->id,
                            'user_message' =>
                            $userMessage,
                            'assistant_message' =>
                            $assistantMessage,
                            'action' =>
                            $result['action'] ?? null,
                        ],
                    ]);
                }
            );
        } catch (AllGeminiModelsExhaustedException $exception) {
            report($exception);

            return response()->json([
                'message' => 'The AI assistant has reached its free usage limit for now. Please try again later.',
                'code' => 'CHAT_QUOTA_EXCEEDED',
            ], 429);
        } catch (GeminiConnectionException $exception) {
            report($exception);

            return response()->json([
                'message' => 'The AI assistant cannot connect right now. Please try again shortly.',
                'code' => 'CHAT_CONNECTION_ERROR',
            ], 503);
        } catch (GeminiTemporaryUnavailableException $exception) {
            report($exception);

            return response()->json([
                'message' => 'The AI assistant is temporarily unavailable. Please try again in a moment.',
                'code' => 'CHAT_TEMPORARILY_UNAVAILABLE',
            ], 503);
        } catch (GeminiRequestException $exception) {
            report($exception);

            return response()->json([
                'message' => "We couldn't complete your message right now. Please try again.",
                'code' => 'CHAT_ERROR',
            ], 500);
        } catch (Throwable $exception) {
            report($exception);


            return response()->json([
                'message' => "We couldn't complete your message right now. Please try again.",
                'code' => 'CHAT_ERROR',
            ], 500);
        }
    }


    public function destroy(
        Request $request,
        ChatConversation $conversation
    ) {
        $this->ensureOwner(
            $request,
            $conversation
        );


        $conversation->delete();


        return response()->json([
            'message' =>
            'Conversation deleted successfully.',
        ]);
    }


    private function ensureOwner(
        Request $request,
        ChatConversation $conversation
    ): void {
        abort_unless(
            $conversation->user_id ===
                $request->user()->id,
            404
        );
    }


    private function createTitle(
        string $message
    ): string {
        return mb_substr(
            trim($message),
            0,
            80
        );
    }
}
