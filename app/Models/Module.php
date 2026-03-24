<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_name',
        'slug',
        'menu_title',
        'parent_menu',
        'status',
        'icon',
        'user_type',
        'order_number',
        'tenant_id',
        'actions',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'actions' => 'array',
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
        return $this->belongsToMany(\App\Models\Agency::class, 'customer_assign');
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