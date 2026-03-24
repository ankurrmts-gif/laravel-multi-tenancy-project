<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{

    use HasFactory;

    protected $table = 'project';

    protected $fillable = [
        'name',
        'summary',
        'cover_image',
        'files',
        'photo',
        'feature_ids'
    ];

    protected $casts = [

    ];

}