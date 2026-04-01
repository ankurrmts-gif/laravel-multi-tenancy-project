<?php
 
namespace App\Mail;
 
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
 
class UserOtpVerifyMail extends Mailable
{
    use Queueable, SerializesModels;
 
    public $otp;
    public $expired;
    public $user;
 
    public function __construct($user,$otp, $expired)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->expired = $expired;
    }
 
    public function build()
    {
        return $this->subject('OTP Verification')
                    ->view('emails.otp_verify');
    }
}
 
 