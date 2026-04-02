<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $table = 'email_tamplate';
    protected $fillable = [
        'title',
        'slug',
        'subject',
        'content',
        'variable',
    ];
    protected $casts = [
        'variable' => 'array',
    ];
}