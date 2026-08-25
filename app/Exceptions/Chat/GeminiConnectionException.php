<?php

namespace App\Exceptions\Chat;

use RuntimeException;
use Throwable;

class GeminiConnectionException extends RuntimeException
{
    public function __construct(
        public readonly string $model,
        ?Throwable $previous = null
    ) {
        parent::__construct('Could not connect to Gemini.', 503, $previous);
    }
}
