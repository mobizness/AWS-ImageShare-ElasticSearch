<?php

namespace App\Http\Controllers\Pub;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\V1\AccountController as ApiAccountController;
use App\Models\Post;
use Illuminate\Support\Collection;
class AccountController extends \App\Http\Controllers\Controller
{
    public function verifyEmail(Request $request, $token)
    {
        if(!User::verifyEmailToken($token)) {
            return view('pub.account.verify-failure');
        }
        
        return view('pub.account.verify-success');
    }
    
    public function restricted(Request $request)
    {
        return view('pub.account.restricted');
    }
    
    public function profile(Request $request, ApiAccountController $api, $id = null)
    {
        $user = null;
        
        if(!is_null($id)) {
            $userData = $api->doShow($id);
            
            if(!is_object($userData)) {
                abort(404);
            }
            
            $user = User::newFromStd($userData);
            
            if(!$user instanceof User) {
                abort(404);
            }
        } else {
            $user = User::newFromStd($api->doShow(Auth::user()->id));
        }
        
        if($user->cant('public-interaction')) {
            return redirect()->route('public.account.restricted');
        }
        
        $feedItems = $api->doFeed($user->id, 'all');
        
        return view('pub.account.profile', compact('user', 'feedItems'));
    }
    
    public function settings(Request $request, ApiAccountController $api)
    {
        $user = \Auth::user();
        return view('pub.account.settings', compact('user'));
    }
}