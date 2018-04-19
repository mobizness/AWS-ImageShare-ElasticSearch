<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Validator;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Laravel\Socialite\AbstractUser;
use GuzzleHttp\Exception\ClientException;
use App\Models\User\Email;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\MessageBag;
use App\Models\User\OAuthToken;
use Carbon\Carbon;
use App\Util\StringUtils;
use Illuminate\Support\Facades\Cookie;
use League\OAuth1\Client\Credentials\TemporaryCredentials;
use App\Exceptions\Auth\DuplicateAccountException;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    
    public function showRegistrationForm()
    {
        return view('pub.auth.register');
    }
    
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'nickname' => 'required|max:255|unique:users',
            'email' => 'required|email|max:255|unique:user_emails',
            'password' => 'required|min:6|confirmed',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        $user = null;
        \DB::transaction(function() use (&$user, $data) {
            $user = User::create([
                'nickname' => $data['nickname'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => $data['first_name'] . ' ' . $data['last_name'],
                'password' => bcrypt($data['password']),
                'avatar' => 'http://placehold.it/150x150'
            ]);
            
            $user->last_login = Carbon::now();

            $user->assignRole('pending');
            
            $user->save();
            
            $email = new Email();
            $email->email = $data['email'];
            $email->user_id = $user->id;
            $email->primary = true;
            $email->save();
            
        });
        
        return $user;
    }
    
    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validator = $this->validator($request->all());
        try {
            $validator->validate();
        } catch(ValidationException $e) {
            
            return response()->json([
                'message' => 'Please correct the following errors',
                'success' => false,
                'errors' => [
                    'validation' => $validator->errors()->toArray()
                ]
            ]);
        }
    
        event(new Registered($user = $this->create($request->all())));
    
        $token = \Auth::login($user);
        
        Cookie::queue('token', $token, \Config::Get('jwt.ttl'));
        
        return response()->json([
            'message' => "",
            'token' => $token,
            'success' => true
        ]);
    }
    
    public function oAuth(Request $request, $driver)
    {
        
        $driver = strtolower($driver);
        
        $socialite = null;
        
        switch($driver) {
            case 'facebook':
            case 'google':
            case 'linkedin':
                $socialite = \Socialite::with($driver)->stateless();
                $socialite->with([
                    'access_type' => 'offline'
                ]);
                
                break;
            case 'twitter':
                $request->session()->start();
                $socialite = \Socialite::with($driver);
                
                break;
            default:
                \App::abort(404);
        }
        
        if(is_null($socialite)) {
            throw new \Exception("Failed to process OAuth");
        }
        
        $scopes = \Config::get("services.$driver.scopes");
        
        if(!empty($scopes)) {
            $socialite->scopes($scopes);
        }
        
        $redirect = $socialite->redirect();

        $response = response()->json([
            'redirect' => $redirect->getTargetUrl()
        ]);
        
        /**
         * SECURITY 
         * 
         * This is a bit hacky. We don't have a session to store OAuth 1.0a temp values
         * for auth (Twitter is the only jerks that don't support OAuth 2.)) so we have to
         * create a cookie to store the value. The cookie is encrypted (make sure you don't remove
         * encrypted cookies from the route middleware) so it *should* be okay but I don't like
         * it. 
         * 
         */
        if($request->session()->has('oauth.temp')) {
            $response->cookie('oauth_temp', serialize($request->session()->get('oauth.temp')), 1, null, null, null, true);
        }
        
        return $response;
    }
    
    public function oAuthCallback(Request $request, $driver)
    {
        $driver = strtolower($driver);

        switch($driver) {
            case 'facebook':
            case 'google':
            case 'linkedin':
                $socialite = \Socialite::driver($driver)->stateless();
                break;
            case 'twitter':
                $socialite = \Socialite::driver($driver);
                break;
            default:
                \App::abort(404);
        }
        
        try {
            switch($driver) {
                case 'facebook':
                case 'google':
                case 'linkedin':
                    $userData = $socialite->user();
                    break;
                case 'twitter':
                    /**
                     * SECURITY
                     * See the static::oAuth() method. This is hacky because we don't 
                     * have a web state for oAuth 1.0a to use. The cookie is encrypted
                     * so it *should* be okay, but we really should revisit this with
                     * a more secure solution. Stupid Twitter doesn't support OAuth 2
                     * for logins. 
                     */
                    $tempValue = $request->cookie('oauth_temp', null);
                    
                    if(is_null($tempValue)) {
                        \Log::error("Error Processing OAuth request - no temp credentials found");
                        
                        throw new \Exception("Error processing OAuth request -- no temp credentials found?");
                    }
                    
                    $tempValue = unserialize($tempValue);
                    
                    if(!$tempValue instanceof TemporaryCredentials) {
                        throw new \Exception("Invalid Temp Credentials Provided!");
                    }
                    
                    $request->session()->set('oauth.temp', $tempValue);
                    
                    $userData = $socialite->user();
                    break;
            }
            
        } catch(\Exception $e) {
            
            \Log::error("Error Processing OAuth request: {$e->getMessage()}", ['exception' => $e]);
            
            return redirect()->route('login');
        }
        
        if(!$userData instanceof AbstractUser) {
            
            \Log::error("Error retrieving user details from OAuth request.", ['userData' => $userData]);
            
            return response()->json([
                'status' => 'failure',
                'message' => 'There was an error processing your request.',
                'error' => app()->environment() == 'local' ? "Error retrieving user details from OAuth request" : ""
            ]);
        }
        
        $user = null;
        
        $isNewUser = false;
        
        try {
            \DB::transaction(function() use ($driver, $userData, &$user, &$isNewUser) {
                
                $oAuthToken = OAuthToken::where('driver', '=', $driver)
                                       ->where('driver_user_id', '=', $userData->getId())
                                       ->limit(1)
                                       ->first();
                    
                // If we have no record of this token
                
                if(!$oAuthToken instanceof OAuthToken) {
                    
                    // But we are logged in, assume our credentials are valid and this is an add-on
                    if(\Auth::check()) {
                        
                        $user = \Auth::user();
                    } else {
                        
                        // Otherwise, create a new user
                        $user = new User();
                        $isNewUser = true;
                    }
                    
                } else {
                    $user = $oAuthToken->user;
                    
                    // If we are logged in but we found this token, and it's not the same user then we've got a dupe.
                    if(\Auth::check()) {
                        if($user->id != \Auth::user()->id) {
                            // We have a different user trying to use the OAuth token, this is a problem.
                            throw new DuplicateAccountException("An account already exists with these credentials");
                        }
                    }
                }
                
                if(empty($user->nickname)) {
                    $user->nickname = $userData->getNickname();
                }
                
                if(empty($user->avatar)) {
                    $user->avatar = $userData->getAvatar();
                }
                
                if(empty($user->name)) {
                    $user->name = $userData->getName();
                }
                
                if(empty($user->password)) {
                    $user->password = \Hash::make(str_random(10));
                }
               
                if(empty($user->first_name) && empty($user->last_name)) {
                    switch($driver) {
                        case 'facebook':
                            break;
                        case 'google':
                            if(isset($userData['name'])) {
                                $user->first_name = $userData['name']['givenName'];
                                $user->last_name = $userData['name']['familyName'];
                            }
                            break;
                        case 'twitter':
                            break;
                        case 'linkedin':
                            break;
                    }
                }
                
                $email = null;
                
                if(!is_null($user->id)) {
                    
                    $userEmail = $userData->getEmail();
                    
                    if(!empty($userEmail)) {
                        $email = $user->emails()->where('email', '=', $userData->getEmail())->first();
                        
                        if(!$email instanceof Email) {
                            
                            $email = new Email();
                            $email->email = $userData->getEmail();
                            $email->user_id = $user->id;
                            $email->verified_at = Carbon::now();
                        }
                        
                        $user->save();
                    }
                    
                } else {
                    
                    $user->save();
                    
                    $userEmail = $userData->getEmail();
                    
                    if(!empty($userEmail)) {
                        $email = new Email();
                        $email->email = $userData->getEmail();
                        $email->user_id = $user->id;
                        $email->verified_at = Carbon::now();
                    }
                    
                }
                
                if(!is_null($email)) {
                    $hasPrimary = $user->emails()->where('primary', '=', true)->limit(1)->first();
                    
                    if(!$hasPrimary instanceof Email) {
                        $email->primary = true;
                        $email->save();
                    }
                }
                
                $user->generateNickname();
                
                if(!$user->hasRole('standard')) {
                    $user->assignRole('standard');
                }
                
                $user->save();
                
                if(!$oAuthToken instanceof OAuthToken) {
                    $oAuthToken = new OAuthToken();
                    $oAuthToken->user_id = $user->id;
                    $oAuthToken->driver_user_id = $userData->getId();
                    $oAuthToken->driver = $driver;
                }
                
                if(!empty($userData->token)) {
                    $oAuthToken->token = $userData->token;
                }
                
                if(!empty($userData->refreshToken)) {
                    $oAuthToken->refresh_token = $userData->refreshToken;
                }
                
                if(!empty($userData->expiresIn)) {
                    $oAuthToken->expires_at = Carbon::now()->addSeconds($userData->expiresIn);
                } 
                
                if(!empty($userData->tokenSecret)) {
                    $oAuthToken->token_secret = $userData->tokenSecret;
                }
                
                $oAuthToken->save();
                
                
            });
            
        } catch(DuplicateAccountException $e) {
            return redirect()->route('auth.oauth.exists');
        }
        
        if(is_null($user)) {
            
            \Log::error("Failed to allocate user object during registration!");
            
            return response()->json([
                'status' => 'failure',
                'message' => 'There was an error processing your request.'
            ]);
        }
        
        if($isNewUser) {
            event(new Registered($user));
        }
        
        $token = \Auth::login($user);
        
        Cookie::queue('token', $token, \Config::Get('jwt.ttl'));

        return redirect()->route('auth.account.profile');
    }
    
    public function dupeAccount(Request $request) {
        return view('pub.auth.dupe');
    }
}
