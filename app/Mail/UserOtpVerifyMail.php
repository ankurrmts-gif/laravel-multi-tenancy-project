<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use App\Models\EmailTemplate;

class UserOtpVerifyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $expired;
    public $user;

    public function __construct($user,$otp,$expired)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->expired = $expired;
    }

    public function build()
    {

        $template = EmailTemplate::where('slug','otp-verify')->first();

        $content = $this->parseTemplate($template->content,[
            'username' => $this->user->first_name,
            'otp' => $this->otp,
            'expiry' => $this->expired,
            'app_name'   => config('app.name'),
            'year' => date('Y')

        ]);

        return $this
            ->subject($template->subject)
            ->html($content);

    }


    private function parseTemplate($content,$data)
    {

        foreach ($data as $key=>$value) {

            $content = str_replace('{{'.$key.'}}',$value,$content);

        }

        return $content;

    }

}