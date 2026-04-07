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

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invitation;
    public $inviter;
    public $frontendUrl;

    public function __construct($invitation, $inviter, $frontendUrl)
    {
        $this->invitation = $invitation;
        $this->inviter = $inviter;
        $this->frontendUrl = $frontendUrl;
    }

    public function build()
    {
        // ✅ Get template from DB
        $template = EmailTemplate::where('slug', 'invitation')->first();

        if (!$template) {
            throw new \Exception('Invitation email template not found');
        }

        // ✅ Prepare data
        $data = [
            'username'        => $this->invitation->first_name.' '.$this->invitation->last_name ?? 'User',
            'inviter_name'    => $this->inviter->first_name.' '.$this->inviter->last_name ?? 'Inviter',
            'inviter_role'    => $this->inviter->getRoleNames()->first(),
            'user_type'       => ucfirst($this->invitation->user_type),
            'expiry_date'     => \Carbon\Carbon::parse($this->invitation->expires_at)->format('d M Y H:i'),
            'invitation_link' => $this->frontendUrl,
            'app_name'        => config('app.name'),
            'year'            => date('Y'),
        ];

        // ✅ Replace variables
        $content = $this->parseTemplate($template->content, $data);

        return $this
        ->subject($template->subject)
        ->html($content);
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