<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use App\Models\EmailTemplate;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Illuminate\Mail\Mailer;
use App\Models\SmtpSetting;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $tenantId; // optional

    public function __construct($user, $tenantId = null)
    {
        $this->user = $user;
        $this->tenantId = $tenantId;
    }

    public function build()
    {
        // ✅ Generate verification URL
        $verificationUrl = $this->generateVerificationUrl();

        // ✅ Get template
        $template = EmailTemplate::where('slug', 'email-verify')->first();

        if (!$template) {
            throw new \Exception('Email verify template not found');
        }

        // ✅ Replace variables
        $content = $this->parseTemplate($template->content, [
            'username' => $this->user->first_name . ' ' . $this->user->last_name ?? 'User',
            'verification_link' => $verificationUrl,
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

    // ✅ Generate URL (tenant + normal)
    private function generateVerificationUrl()
    {
        if ($this->tenantId) {
            return URL::temporarySignedRoute(
                'tenant.verification.verify',
                Carbon::now()->addMinutes(60),
                [
                    'tenant' => $this->tenantId,
                    'id'     => $this->user->id,
                    'hash'   => sha1($this->user->getEmailForVerification()),
                ]
            );
        }

        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id'   => $this->user->id,
                'hash' => sha1($this->user->getEmailForVerification()),
            ]
        );
    }

    // ✅ Helper
    private function parseTemplate($content, $data)
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }
        return $content;
    }
}