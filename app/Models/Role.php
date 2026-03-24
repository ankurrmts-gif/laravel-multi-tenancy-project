<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $connection = null; // dynamically set

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (tenant()) {
            $this->setConnection('tenant');
        } else {
            $centralConnection = config('tenancy.database.central_connection', config('database.default'));
            if (!array_key_exists($centralConnection, config('database.connections', []))) {
                $centralConnection = config('database.default');
            }
            $this->setConnection($centralConnection);
        }
    }
}
