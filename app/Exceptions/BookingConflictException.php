<?php

namespace App\Exceptions;

use RuntimeException;

class BookingConflictException extends RuntimeException
{
    public function __construct(string $message = 'The selected time is no longer available. Please choose another time.')
    {
        parent::__construct($message);
    }
}
