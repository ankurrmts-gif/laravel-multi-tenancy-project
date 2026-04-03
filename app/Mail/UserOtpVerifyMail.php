<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use App\Models\EmailTemplate;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Illuminate\Mail\Mailer;
use App\Models\SmtpSetting;

class UserOtpVerifyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $expired;
    public $user;

    public function __construct($user, $otp, $expired)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->expired = $expired;
    }

    public function build()
    {
        // ✅ Get template from DB
        $template = EmailTemplate::where('slug', 'otp-verify')->first();

        if (!$template) {
            throw new \Exception('OTP email template not found');
        }

        // ✅ Replace variables
        $content = $this->parseTemplate($template->content, [
            'username' => $this->user->first_name.' '.$this->user->last_name ?? 'User',
            'otp' => $this->otp,
            'expiry' => $this->expired,
            'app_name' => config('app.name'),
            'year' => date('Y'),
        ]);

       if($this->user->user_type == 'tenant' && $this->user->tenant_id) {
            tenancy()->initialize($this->user->tenant_id);
                $smtp = SmtpSetting::first();
            tenancy()->end();
        } else {
            $smtp = SmtpSetting::first();
        }

            $transport = new EsmtpTransport(
                $smtp->host,
                $smtp->port,
                $smtp->encryption
            );

            $transport->setUsername($smtp->username);
            $transport->setPassword($smtp->password);


            $customMailer = new Mailer(
                'dynamic',
                app('view'),
                $transport, // correct
                app('events')
            );


            $customMailer->alwaysFrom(
                $smtp->from_address,
                $smtp->from_name
            );

        return $this
            ->subject($template->subject)
            ->html($content)
            ->mailer($customMailer);
    }

    // ✅ Helper function
    private function parseTemplate($content, $data)
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }
        return $content;
    }
}