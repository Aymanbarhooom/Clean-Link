<?php

namespace App\Exceptions\Chat;

use RuntimeException;

class GeminiQuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $model,
        public readonly int $status = 429,
        public readonly ?string $quotaId = null,
        public readonly ?string $retryDelay = null,
        string $message = 'Gemini model quota exhausted.'
    ) {
        parent::__construct($message, $status);
    }
}
