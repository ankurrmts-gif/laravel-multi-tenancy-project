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

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $resetUrl;

    public function __construct($user, $resetUrl)
    {
        $this->user = $user;
        $this->resetUrl = $resetUrl;
    }

    public function build()
    {
        // ✅ Get template
        $template = EmailTemplate::where('slug', 'reset-pass')->first();

        if (!$template) {
            throw new \Exception('Reset password template not found');
        }

        // ✅ Replace variables
        $content = $this->parseTemplate($template->content, [
            'username'   => $this->user->first_name . ' ' . $this->user->last_name ?? 'User',
            'reset_link' => $this->resetUrl,
            'app_name'   => config('app.name'),
            'year'       => date('Y'),
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

    private function parseTemplate($content, $data)
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }
        return $content;
    }
}