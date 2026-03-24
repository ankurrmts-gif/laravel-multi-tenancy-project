<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_name',
        'menu_title',
        'parent_menu',
        'status',
        'user_type',
        'icon',
        'order_number',
        'tenant_id',
        'actions'
    ];

    protected $casts = [
        'actions' => 'array',
        'status' => 'boolean',
    ];
}
