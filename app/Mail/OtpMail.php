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
        return $this->subject('رمز التحقق من التسجيل')
                    ->view('emails.otp');
    }
}
