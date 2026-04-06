<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatatableSetting extends Model
{
    protected $table = 'datatable_settings';
    protected $fillable = [
        'table_key',
        'module_type',
        'module_id',
        'settings',
    ];
    protected $casts = [
        'settings' => 'array',
    ];
}