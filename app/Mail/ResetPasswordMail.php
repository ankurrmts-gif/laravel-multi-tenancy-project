<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use App\Models\EmailTemplate;

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

        return $this->subject($template->subject)
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