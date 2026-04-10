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
use App\Models\Settings;

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
        if($this->user->user_type == 'tenant'){
            tenancy()->initialize($this->user->tenant_id);
        }else{
            tenancy()->end();
        }
        // ✅ Get template
        $template = EmailTemplate::where('slug', 'reset-pass')->first();
        $Settings = Settings::pluck('value', 'key')->toArray();

        if (!$template) {
            throw new \Exception('Reset password template not found');
        }

        // ✅ Replace variables
        $data = array_merge([
            'username'   => $this->user->first_name . ' ' . $this->user->last_name ?? 'User',
            'reset_link' => $this->resetUrl,
            'app_name'   => config('app.name'),
            'year'       => date('Y'),
            'logo' => asset($Settings['logo']),
        ], $Settings);

        // ✅ Replace variables
        $content = $this->parseTemplate($template->content, $data);

       return $this
        ->subject($template->subject)
        ->html($content);
    }

    private function parseTemplate($content, $data)
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }
        return $content;
    }
}