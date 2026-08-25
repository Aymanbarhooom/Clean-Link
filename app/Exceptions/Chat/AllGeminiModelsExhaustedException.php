<?php

namespace App\Exceptions\Chat;

use RuntimeException;

class AllGeminiModelsExhaustedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('All configured Gemini model quotas are exhausted.', 429);
    }
}
