<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColumnType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'input_type',
        'db_type',
        'has_options',
        'is_active',
    ];

    protected $casts = [
        'has_options' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function moduleFields()
    {
        return $this->hasMany(ModuleField::class);
    }
}