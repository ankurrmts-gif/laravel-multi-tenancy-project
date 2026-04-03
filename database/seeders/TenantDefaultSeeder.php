<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SmtpSetting;
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
                'value' => 'uploads/defalut_logo/f1774604035_69c64f03e7de7.jpeg',
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
    }
}
