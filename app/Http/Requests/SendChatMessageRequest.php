<?php


namespace App\Http\Requests;


use Illuminate\Foundation\Http\FormRequest;


class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'max:2000',
            ],
            'conversation_id' => [
                'nullable',
                'integer',
                'exists:chat_conversations,id',
            ],
        ];
    }
}
