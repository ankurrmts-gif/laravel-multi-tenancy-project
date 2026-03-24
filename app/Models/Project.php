<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $table = 'projects';
    protected $fillable = [
        'name',
        'summary',
        'feature_ids',
    ];

    public function features()
    {
        return $this->belongsToMany(
            \App\Models\Feature::class,
            'features_projects',
            'project_id',
            'feature_id'
        )->withTimestamps();
    }
}
