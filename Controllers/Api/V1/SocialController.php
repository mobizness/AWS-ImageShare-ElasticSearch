<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
class SocialController extends Controller
{
    public function doFollow(User $follower, User $following)
    {
        \DB::transaction(function() use ($follower, $following) {
        
            $follower->following()->attach($following->id);
        
            $follower->increment('following_count');
            $following->increment('follower_count');
        
            $follower->save();
            $following->save();
        });
    }
    
    public function doUnfollow(User $follower, User $following)
    {
        \DB::transaction(function() use ($follower, $following) {
            $follower->following()->detach($following->id);
        
            $follower->decrement('following_count');
            $following->decrement('follower_count');
        
            $follower->save();
            $following->save();
        });
    }
    
    public function follow(Request $request, $id)
    {
        $response = [
            'success' => false,
            'error' => 'Unknown error'
        ];
        
        try {
            $followAcct = User::findOrFail($id);
            
            if($request->user()->cant('public-interaction')) {
                $this->unauthorized();
            }
            
            if($followAcct->cant('public-interaction')) {
                $this->unauthorized();
            }
            
            $this->doFollow($request->user(), $followAcct);
            
            $response['success'] = true;
            $resposne['error'] = null;
            
        } catch(\Exception $e) {
            $response['success'] = false;
            $response['error'] = app()->environment() != 'production' ? $e->getMessage() : 'Error on following this user.';
            
            if(app()->environment() != 'production') {
                throw $e;
            }
            
            \Log::error("An error occurred ", ['exception' => $e]);
        } finally {
            return $response;
        }
    }
    
    public function unfollow(Request $request, $id)
    {
        $response = [
            'success' => false,
            'error' => 'Unknown error'
        ];
        
        try {
            
            $followAcct = User::findOrFail($id);
            
            $this->doUnfollow($request->user(), $followAcct);
            
            $response['success'] = true;
            $response['error'] = null;
            
        } catch(\Exception $e) {
            $response['success'] = false;
            $response['error'] = app()->environment() != 'production' ? $e->getMessage() : 'Error on unfollowing this user.';
            
            \Log::error("An error occured ", ['exception' => $e]);
            
        } finally {
            return $response;
        }
    }
}