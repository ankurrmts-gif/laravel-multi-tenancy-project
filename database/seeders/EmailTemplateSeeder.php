<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [

            // ✅ OTP TEMPLATE
            [
    'title'   => 'OTP Verification',
    'subject' => 'Verify Your Account - OTP',
    'slug'    => 'otp-verify',
    'variable' => [
        'username',
        'otp',
        'expiry',
        'app_name',
        'year'
    ],
    'content' => '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Verify Your Account</title>
</head>

<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">

<div style="max-width:600px;margin:20px auto;background:#ffffff;border-radius:10px;overflow:hidden;">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:30px;text-align:center;">
        <div style="font-size:40px;margin-bottom:10px;">✓</div>
        <h2 style="margin:0;">Verify Your Account</h2>
        <p style="margin:5px 0 0;">Secure Login Verification</p>
    </div>

    <!-- Content -->
    <div style="padding:30px;color:#333;line-height:1.6;">

        <p>Hello <strong>{{username}}</strong>,</p>

        <p>
        We received a request to access your account. Use the OTP below to continue.
        </p>

        <!-- OTP -->
        <div style="background:#f4f6ff;border:2px dashed #667eea;border-radius:8px;padding:20px;text-align:center;margin:25px 0;">
            <div style="font-size:12px;color:#999;margin-bottom:8px;">Your OTP</div>
            <div style="font-size:36px;font-weight:bold;color:#667eea;letter-spacing:6px;">
                {{otp}}
            </div>
        </div>

        <!-- Expiry -->
        <div style="background:#fff3cd;padding:12px;border-left:4px solid #ffc107;margin-bottom:20px;">
            ⏰ This code expires in <strong>{{expiry}}</strong> minutes
        </div>

        <!-- Steps -->
        <p><strong>How to use:</strong></p>
        <ol style="padding-left:18px;">
            <li>Copy the OTP</li>
            <li>Paste in verification screen</li>
            <li>Click verify</li>
        </ol>

        <!-- Security -->
        <div style="background:#f8f9fa;border:1px solid #ddd;padding:15px;margin-top:20px;">
            <p><strong>🔒 Do not share this OTP</strong></p>
            <p>If you didn’t request this, ignore this email.</p>
        </div>

    </div>

    <!-- Footer -->
    <div style="background:#f1f1f1;text-align:center;padding:15px;font-size:12px;color:#777;">
        <strong>© {{year}} {{app_name}}</strong><br>
        This is an automated message. Please do not reply.
    </div>

</div>

</body>
</html>
    ',
],

            // ✅ INVITATION TEMPLATE
            [
                'title'   => 'Invitation',
                'subject' => 'You are invited to join',
                'slug'    => 'invitation',
                'variable' => [
                    'username',
                    'inviter_name',
                    'inviter_role',
                    'user_type',
                    'expiry_date',
                    'invitation_link',
                    'app_name',
                    'year'
                ],
                'content' => '
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:20px;font-family:Arial,sans-serif;">
<tr><td align="center">

<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;">

<tr>
<td style="background:#667eea;color:#fff;padding:30px;text-align:center;">
    <h2>You\'re Invited 🎉</h2>
</td>
</tr>

<tr>
<td style="padding:30px;">

<p>Hello <strong>{{username}}</strong>,</p>

<p>
You are invited by <strong>{{inviter_name}}</strong> ({{inviter_role}})
to join as <strong>{{user_type}}</strong>.
</p>

<p><strong>Expiry:</strong> {{expiry_date}}</p>

<div style="text-align:center;margin:25px 0;">
    <a href="{{invitation_link}}" style="background:#667eea;color:#fff;padding:12px 25px;text-decoration:none;border-radius:5px;">
        Activate Account
    </a>
</div>

<p>{{invitation_link}}</p>

</td>
</tr>

<tr>
<td style="background:#f1f1f1;text-align:center;padding:15px;">
© {{year}} {{app_name}}
</td>
</tr>

</table>

</td></tr>
</table>
                ',
            ],

            // ✅ CONTACT US
            [
                'title'   => 'Contact Us',
                'subject' => 'New Contact Message',
                'slug'    => 'contact-us',
                'variable' => [
                    'username',
                    'email',
                    'message'
                ],
                'content' => '
<p><strong>Name:</strong> {{username}}</p>
<p><strong>Email:</strong> {{email}}</p>
<p><strong>Message:</strong></p>
<p>{{message}}</p>
                ',
            ],

            // ✅ EMAIL VERIFY
            [
                'title'   => 'Email Verification',
                'subject' => 'Verify Your Email',
                'slug'    => 'email-verify',
                'variable' => [
                    'username',
                    'verification_link',
                    'app_name',
                    'year'
                ],
                'content' => '
<p>Hello {{username}},</p>

<p>Click below to verify your email:</p>

<p>
<a href="{{verification_link}}" style="background:#28a745;color:#fff;padding:10px 20px;text-decoration:none;">
Verify Email
</a>
</p>

<p>© {{year}} {{app_name}}</p>
                ',
            ],

            // ✅ RESET PASSWORD
            [
                'title'   => 'Reset Password',
                'subject' => 'Reset Your Password',
                'slug'    => 'reset-pass',
                'variable' => [
                    'username',
                    'reset_link',
                    'app_name',
                    'year'
                ],
                'content' => '
<p>Hello {{username}},</p>

<p>Click below to reset your password:</p>

<p>
<a href="{{reset_link}}" style="background:#dc3545;color:#fff;padding:10px 20px;text-decoration:none;">
Reset Password
</a>
</p>

<p>© {{year}} {{app_name}}</p>
                ',
            ],

        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}