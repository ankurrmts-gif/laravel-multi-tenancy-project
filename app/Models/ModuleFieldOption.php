<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleFieldOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_field_id',
        'option_label',
        'option_value',
    ];

    public function moduleField()
    {
        return $this->belongsTo(\App\Models\ModuleField::class);
    }
}