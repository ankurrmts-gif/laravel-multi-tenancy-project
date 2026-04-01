<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOtp extends Model
{
    protected $table = 'users_otp';
    protected $fillable = [
        'user_id',
        'user_type',
        'tenant_id',
        'otp',
        'expires_at',
    ];
}
