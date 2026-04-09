<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SmtpSetting;
use App\Models\EmailTemplate;
use App\Models\Settings;

class TenantDefaultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

          /** -------------------------------------------------
         * ALWAYS USE CENTRAL DB
         * -------------------------------------------------*/

        Settings::insert([
            // 🌐 GENERAL SETTINGS
            [
                'key' => 'site_email',
                'value' => 'ankur.r.mts@gmail.com',
                'type' => 'input',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_phone',
                'value' => '+91 1234567890',
                'type' => 'input',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'footer_text',
                'value' => 'Keenthemes Inc.',
                'type' => 'input',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'primary_color',
                'value' => '#1234',
                'type' => 'color',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'secondary_color',
                'value' => '#5678',
                'type' => 'color',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 🖼️ FILES
            [
                'key' => 'favicon_icon',
                'value' => 'uploads/defalut_logo/1774604035_69c64f03e7de7.jpeg',
                'type' => 'file',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'logo',
                'value' => 'uploads/defalut_logo/1775027152_69ccc3d04ee52.svg',
                'type' => 'file',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mini_logo',
                'value' => 'uploads/defalut_logo/1775027152_69ccc3d04db28.svg',
                'type' => 'file',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_logo_dark',
                'value' => 'uploads/defalut_logo/1775027152_69ccc3d0504a1.svg',
                'type' => 'file',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mini_logo_dark',
                'value' => 'uploads/defalut_logo/1775027152_69ccc3d051415.svg',
                'type' => 'file',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 🔗 SOCIAL MEDIA
            [
                'key' => 'facebook',
                'value' => 'https://www.facebook.com',
                'type' => 'input',
                'group' => 'social_media',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'instagram',
                'value' => 'https://www.instagram.com',
                'type' => 'input',
                'group' => 'social_media',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'x',
                'value' => 'https://www.x.com',
                'type' => 'input',
                'group' => 'social_media',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'linkedin',
                'value' => 'https://www.linkedin.com',
                'type' => 'input',
                'group' => 'social_media',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'youtube',
                'value' => 'https://www.youtube.com',
                'type' => 'input',
                'group' => 'social_media',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 📍 CONTACT INFO
            [
                'key' => 'address',
                'value' => 'address',
                'type' => 'textarea',
                'group' => 'contact_info',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'google_map_link',
                'value' => 'google_map_link',
                'type' => 'input',
                'group' => 'contact_info',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'support_email',
                'value' => 'ankur.r.mts@gmail.com',
                'type' => 'input',
                'group' => 'contact_info',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'support_phone',
                'value' => '+91 1234567890',
                'type' => 'input',
                'group' => 'contact_info',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 📊 SEO
            [
                'key' => 'meta_title',
                'value' => 'meta_title',
                'type' => 'input',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'meta_description',
                'value' => 'meta_description',
                'type' => 'textarea',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'meta_keywords',
                'value' => 'meta_keywords',
                'type' => 'textarea',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ]);

        SmtpSetting::insert([
            'mailer' => 'smtp',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'ankur.r.mts@gmail.com',
            'password' => 'ngbj xdyc rrkl rydf',
            'encryption' => 'SSL',
            'from_address' => 'ankur.r.mts@gmail.com',
            'from_name' => env('APP_NAME')
        ]);

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
                    'year',
                    'footer_text',
                    'site_name',
                    'site_email',
                    'site_phone',
                    'facebook',
                    'instagram',
                    'x',
                    'linkedin',
                    'youtube',
                    'address',
                    'logo',
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
                    'year',
                    'footer_text',
                    'site_name',
                    'site_email',
                    'site_phone',
                    'facebook',
                    'instagram',
                    'x',
                    'linkedin',
                    'youtube',
                    'address',
                    'logo',
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
                    </table>',
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
                    'year',
                    'footer_text',
                    'site_name',
                    'site_email',
                    'site_phone',
                    'facebook',
                    'instagram',
                    'x',
                    'linkedin',
                    'youtube',
                    'address',
                    'logo',
                ],
                'content' => '
                        <p>Hello {{username}},</p>

                        <p>Click below to reset your password:</p>

                        <p>
                        <a href="{{reset_link}}" style="background:#dc3545;color:#fff;padding:10px 20px;text-decoration:none;">
                        Reset Password
                        </a>
                        </p>

                        <p>© {{year}} {{app_name}}</p>',
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
