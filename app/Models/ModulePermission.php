<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModulePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'user_id',
        'permission_name',
    ];

    public function module()
    {
        return $this->belongsTo(\App\Models\Module::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}