<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'main_model_name',
        'slug',
        'menu_title',
        'parent_menu',
        'status',
        'icon',
        'user_type',
        'order_number',
        'tenant_id',
        'tenant_user_type',
        'actions',
        'permissions',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'actions' => 'array',
        'permissions' => 'array',
    ];

    public function fields()
    {
        return $this->hasMany(\App\Models\ModuleField::class);
    }

    public function assignedAdmins()
    {
        return $this->belongsToMany(\App\Models\User::class, 'admin_assign');
    }

    public function assignedAgencies()
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'customer_assign',
            'module_id',
            'agency_id'
        );
    }

    public function permissions()
    {
        return $this->hasMany(\App\Models\ModulePermission::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
