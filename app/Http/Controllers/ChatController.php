<?php


namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
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
    ) {
    }


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


    public function send(
        SendChatMessageRequest $request
    ) {
        $user = $request->user();


        $conversation = null;


        if ($request->filled('conversation_id')) {
            $conversation =
                ChatConversation::query()
                    ->where(
                        'id',
                        $request->integer(
                            'conversation_id'
                        )
                    )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->firstOrFail();
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
                    if (!$conversation) {
                        $conversation =
                            ChatConversation::create([
                                'user_id' =>
                                    $user->id,
                                'title' =>
                                    $this->createTitle(
                                        $request->string(
                                            'message'
                                        )->toString()
                                    ),
                            ]);
                    }


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
                                'gemini_response_id' =>
                                    $result[
                                        'response_id'
                                    ] ?? null,
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
                        ],
                    ]);
                }
            );
        } catch (Throwable $exception) {
            report($exception);


            return response()->json([
                'message' =>
                    'Chat service is currently unavailable.',
            ], 503);
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
