<?php
 
namespace App\Models;
 
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
 
class Settings extends Model
{
    use HasFactory;
 
    public $table = 'settings';
 
    protected $dates = [
        'created_at',
        'updated_at',
    ];
 
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'created_at',
        'updated_at',
    ];
 
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}