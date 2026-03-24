<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    use HasFactory;

    protected $table = 'feature';

    protected $fillable = [
        'name',
        'description'
    ];

    protected $casts = [

    ];

    /**
     * Get the projects that have this feature.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            Project::class,
            'project_feature',
            'feature_id',
            'project_id'
        )->withTimestamps();
    }
}