<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ActivityLog extends Model{
    protected $fillable=['user_id','user_name','module','action','description','ip_address','user_agent','meta'];
    protected $casts=['meta'=>'array'];
}