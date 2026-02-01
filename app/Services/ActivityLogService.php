<?php
namespace App\Services;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
class ActivityLogService{
    public function log($module,$action,$description=null,$meta=null,Request $request=null){
        $u=Auth::user();
        return ActivityLog::create([
            'user_id'=>$u->id??null,
            'user_name'=>$u->name??null,
            'module'=>$module,
            'action'=>$action,
            'description'=>$description,
            'ip_address'=>$request?->ip(),
            'user_agent'=>$request?->header('User-Agent'),
            'meta'=>$meta
        ]);
    }
}