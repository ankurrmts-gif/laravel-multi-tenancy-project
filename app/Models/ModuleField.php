<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleField extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'column_type_id',
        'db_column',
        'label',
        'tooltip_text',
        'validation',
        'default_value',
        'status',
        'is_ckeditor',
        'is_multiple',
        'model_name',
        'model_field_name',
        'max_file_size',
        'order_number',
        'is_checked',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_ckeditor' => 'boolean',
        'is_multiple' => 'boolean',
        'is_checked' => 'boolean',
    ];

    public function module()
    {
        return $this->belongsTo(\App\Models\Module::class);
    }

    public function columnType()
    {
        return $this->belongsTo(\App\Models\ColumnType::class);
    }

    public function options()
    {
        return $this->hasMany(\App\Models\ModuleFieldOption::class);
    }
}