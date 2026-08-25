<?php

namespace App\Exceptions\Chat;

use RuntimeException;

class GeminiTemporaryUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $model,
        public readonly int $status,
        string $message = 'Gemini is temporarily unavailable.'
    ) {
        parent::__construct($message, $status);
    }
}
