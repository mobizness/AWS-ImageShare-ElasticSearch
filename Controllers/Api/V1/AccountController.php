<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use App\Models\User\Email;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if($request->user()->cant('manage-all-accounts')) {
            $this->unauthorized();
        }
        
        return User::paginate($request['page_size']);
    }
    
    public function doFeed($id, $list)
    {
        $user = User::find($id);
        
        if(!$user instanceof User) {
            return null;
        }
        
        return $user->feed($list);
    }
    
    public function feed(Request $request, $id, $list)
    {
        if(!\Auth::check()) {
            $this->unauthorized();
        }
        
        if($request->user()->id != $id) {
            if($request->user()->cant('manage-all-accounts')) {
                $this->unauthorized();
            }
        }
        
        if($request->user()->cant('account-manage')) {
            $this->unauthorized();
        }
        
        return $this->doFeed($id, $list);
    }

    public function doShow($id)
    {
        return User::with('emails')->find($id);
    }
    
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        if(!\Auth::check()) {
            $this->unauthorized();
        }
        
        if($id != $request->user()->id) {
            if($request->user()->cant('manage-all-accounts')) {
                $this->unauthorized();
            }
        }
        
        if($request->user()->cant('account-manage')) {
            $this->unauthorized();
        }

        return doShow($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if($id != $request->user()->id) {
            if($request->user()->cant('manage-all-accounts')) {
                $this->unauthorized();
            }
        }
        
        if($request->user()->cant('account-manage')) {
            $this->unauthorized();
        }
        
        $this->validate($request, [
            'first_name' => 'sometimes|max:255',
            'last_name' => 'sometimes|max:255',
            'nickname' => 'sometimes|max:255|unique:users',
            'emails.*' => 'required_with:emails|email',
            'emails' => 'sometimes|array|min:1',
            'password' => 'soemtimes|min:6|confirmed',
            'avatar' => 'sometimes|url'
        ]);
        
        $input = $request->only($request->only([
            'first_name', 'last_name', 'nickname',
            'password', 'avatar'
        ]));
        
        $input['emails'] = $request->input('emails', []);
        
        \DB::transaction(function() use ($request, $input) {
            if(!empty($input['emails'])) {
                
                $request->user()->emails()->delete();
                $first = true;
                
                foreach($input['emails'] as $address) {
                    $email = new Email();
                    $email->email = $address;
                    $email->user_id = $request->user()->id;
                    
                    if($first) {
                        $email->primary = true;
                        $first = false;
                    }
                    $email->save();
                }
            }
        
            $request->user()->update($input);
        });
        
        return User::with('emails')->find($request->user()->id);
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        if($id != $request->user()->id) {
            if($request->user()->cant('manage-all-accounts')) {
                $this->unauthorized();
            }
        }
        
        if($request->user()->cant('account-manage')) {
            $this->unauthorized();
        }
        
        $user = User::findOrFail($id);
        $user->delete();
        
        return response([
            'success' => true
        ]);
    }
    
}