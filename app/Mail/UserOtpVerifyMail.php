<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use App\Models\EmailTemplate;
use App\Models\Settings;

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

        if($this->user->user_type == 'tenant'){
            tenancy()->initialize($this->user->tenant_id);
        }else{
            tenancy()->end();
        }

        $template = EmailTemplate::where('slug','otp-verify')->first();
        $Settings = Settings::pluck('value', 'key')->toArray();

        // ✅ Replace variables
        $data = array_merge([
            'username' => $this->user->first_name,
            'otp' => $this->otp,
            'expiry' => $this->expired,
            'app_name'   => config('app.name'),
            'year' => date('Y'),
            'logo' => asset($Settings['logo']),
        ], $Settings);

        // ✅ Replace variables
        $content = $this->parseTemplate($template->content, $data);

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