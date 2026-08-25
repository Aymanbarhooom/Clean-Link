<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ChatMessage extends Model
{
    protected $fillable = [
        'chat_conversation_id',
        'role',
        'content',
        'action',
        'gemini_response_id',
    ];

    protected $casts = ['action' => 'array'];


    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            ChatConversation::class,
            'chat_conversation_id'
        );
    }
}
