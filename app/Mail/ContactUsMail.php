<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use App\Models\EmailTemplate;

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