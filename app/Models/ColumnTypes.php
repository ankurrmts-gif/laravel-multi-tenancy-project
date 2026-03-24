<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ColumnTypes extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'input_type',
        'db_type',
        'has_options',
        'is_active'
    ];
}
