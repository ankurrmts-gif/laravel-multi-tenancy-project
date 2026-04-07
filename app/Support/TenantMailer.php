<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use App\Models\SmtpSetting;

class TenantMailer
{

    public static function apply($user = null)
    {

        /*
        |--------------------------------------------------------------------------
        | get smtp settings
        |--------------------------------------------------------------------------
        */

        if ($user && $user->user_type === 'tenant' && $user->tenant_id) {

            tenancy()->initialize($user->tenant_id);

            $smtp = SmtpSetting::first();

            tenancy()->end();

        } else {

            // central smtp
            $smtp = SmtpSetting::first();

        }


        if (!$smtp) {

            throw new \Exception('SMTP setting not found');

        }


        /*
        |--------------------------------------------------------------------------
        | set dynamic mail config
        |--------------------------------------------------------------------------
        */

        Config::set('mail.default', 'dynamic');

        Config::set('mail.mailers.dynamic', [

            'transport' => 'smtp',

            'host' => $smtp->host,

            'port' => $smtp->port,

            'encryption' => $smtp->encryption,

            'username' => $smtp->username,

            'password' => $smtp->password,

            'timeout' => null,

        ]);


        Config::set('mail.from.address', $smtp->from_address);

        Config::set('mail.from.name', $smtp->from_name);


        /*
        |--------------------------------------------------------------------------
        | clear old mail instance
        |--------------------------------------------------------------------------
        */

        app()->forgetInstance('mail.manager');

        app()->forgetInstance(\Illuminate\Contracts\Mail\Factory::class);

    }

}