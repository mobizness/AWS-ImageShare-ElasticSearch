<?php

namespace App\Http\Controllers\Pub;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User\Notification;

class NotificationController extends Controller
{
    public function display(Request $request) 
    {
        $notifications = $request->user()
                                 ->notifications()
                                 ->paginate(10);
        
        Notification::whereIn('id', $notifications->pluck('id'))
                    ->update(['read_at' => Carbon::now()]);
        
        return view('pub.account.notifications', compact('notifications'));
    }
}