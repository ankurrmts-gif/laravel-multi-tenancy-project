<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentralTenantTelations extends Model
{   
    use HasFactory;

    protected $connection = 'mysql';

    // Ensure this model uses the central database and the correct table name
    protected $table = 'central_tenant_relations';

    // protected $connection;

    // public function __construct(array $attributes = [])
    // {
    //     parent::__construct($attributes);
    //     // Use central connection configured for the tenancy package
    //     $this->connection = config('tenancy.central_connection', config('database.default'));
    // }

    protected $fillable = ['email','tenant_id','status'];
}
