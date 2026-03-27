<?php
 
namespace App\Models;
 
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
 
class UserInvitations extends Model
{
    use HasFactory;
 
    public $table = 'super_admin_invitations';
 
    protected $dates = [
        'created_at',
        'updated_at',
        'expires_at',
    ];
 
    protected $casts = [
        'expires_at' => 'datetime',
    ];
 
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'token',
        'user_type',
        'tenant_id',
        'status',
        'expires_at',
        'created_by',
        'created_at',
        'updated_at',
    ];
 
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}