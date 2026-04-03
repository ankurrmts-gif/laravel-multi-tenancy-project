<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Illuminate\Mail\Mailer;
use App\Models\EmailTemplate;
use App\Models\SmtpSetting;

class ContactUsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        // ✅ Get template from DB
        $template = EmailTemplate::where('slug', 'contact-us')->first();

        if (!$template) {
            throw new \Exception('Contact email template not found');
        }

        // ✅ Replace variables
        $content = $this->parseTemplate($template->content, [
            'username' => $this->data['name'],
            'email'    => $this->data['email'],
            'message'  => $this->data['message'],
        ]);

        $smtp = SmtpSetting::first();

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