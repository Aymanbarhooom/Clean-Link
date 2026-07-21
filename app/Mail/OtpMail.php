<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otpCode;
    public $fullname;

    public function __construct($otpCode, $fullname)
    {
        $this->otpCode = $otpCode;
        $this->fullname = $fullname;
    }

    public function build()
    {
        return $this->subject('ٌRegistration One Time Password')
                    ->view('emails.otp');
    }
}
