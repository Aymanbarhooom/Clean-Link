<?php

namespace App\Exceptions\Chat;

use RuntimeException;

class GeminiRequestException extends RuntimeException
{
    public function __construct(
        public readonly string $model,
        public readonly int $status,
        string $message = 'Gemini rejected the request.'
    ) {
        parent::__construct($message, $status);
    }
}
