<?php
namespace App\Http\Controllers\Backend;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class ActivityLogController extends Controller{
    public function index(Request $r){
        $q=ActivityLog::query()->latest();
        if($r->q) $q->where('description','like','%'.$r->q.'%');
        $logs=$q->paginate(25);
        return view('backend.activity.index',compact('logs'));
    }
}