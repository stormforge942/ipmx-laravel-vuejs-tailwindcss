<?php

namespace App\Http\Controllers;

use App\Exceptions\FEException;
use App\Helpers\Media;
use App\Jobs\ImageProcess;
use Illuminate\Http\Request;
use Hash;
use App\Setting;
use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Autheticate a user.
     *
     * @param  $request
     * @return \Illuminate\Http\Response
     */
    protected function auth(Request $request)
    {
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            throw new FEException(__('Your credentials are incorrect. Please try again'), '', 500);
        }
		
        return $this->login(Auth::user());;

    }
    /**
     * Login a user.
     *
     * @param  $request
     * @return \Illuminate\Http\Response
     */
    protected function login($user)
    {
        
        if (!Auth::check()) {
            Auth::login($user);
        }

        if( Auth::user() &&  Auth::user()->blocked == 1) {
            Auth::logout();
            throw new FEException(__('Your account is suspended.'), '', 500);
        }
        
        if (!Auth::user()) {
            throw new FEException(__('Your credentials are incorrect. Please try again'), '', 500);
        }
        $scopes = $this->userScopes(Auth::user());
		Auth::user()->tokens()->delete();
        $token = Auth::user()->createToken('access_token', $scopes)->accessToken;
   
        return response(['access_token' => $token]);
    }

    /**
     * Obtain the user information from OAuth provider.
     * @param $request
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback(Request $request, $driver)
    {
        if ($driver === 'google') {
            $user = User::firstOrCreate(
                [
                    'email' => $request->profile['email']
                ],
                [
                    'name' => $request->profile['name'],
                    'password' => Hash::make(\Str::random(24))
                ]
            );

            Media::delete($user, 'avatar');
            Media::updateImageFromURL($user, $request->profile['avatar'], 'avatar');
        } 
        else if ($driver === 'facebook') {
            if (User::where('facebook_id', $request->profile['id'])->first()) {
                return $this->login(User::where('facebook_id', $request->profile['id'])->first());
            }
            if (!isset($request->profile['email'])) {
                throw new FEException(__('Email was not recieved by the facebook provider, Try another login method.'), '', 500);
            } else {
                $user = User::firstOrCreate(
                    [
                        'email' => $request->profile['email']
                    ],
                    [
                        'facebook_id' => $request->profile['id'],
                        'name' => $request->profile['name'],
                        'password' => Hash::make(\Str::random(24))
                    ]
                );
                Media::delete($user, 'avatar');
                Media::updateImageFromURL($user, $request->profile['avatar'], 'avatar');
            }
        }
        return $this->login($user);
    }

    /**
     * Get the user scopes.
     * @param $request
     * @return \Illuminate\Http\Response
     */
    public function userScopes($user)
    {
        $scopes = [];
        if ($user->is('user')) {
            array_push(
                $scopes,
                'user_scope'
            );
        }
        if ($user->is('artist')) {
            array_push(
                $scopes,
                'manage_own_content'
            );
        }
        if ($user->is('admin') || $user->is_admin) {
            array_push(
                $scopes,
                'manage_own_content',
                'manage_everything'
            );
        }
        if ($user->is_super_admin) {
            array_push(
                $scopes,
                'do_anything'
            );
        }
        return $scopes;
    }

    /**
     * Register a user.
     * @param $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);
        if (User::where('email', $request->email)->first()) {
            throw new FEException(__('Account already exists.'), '', 500);
        }
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'available_disk_space' => floatval(Setting::get('availableUserDiskSpace')),
            'lang' => Setting::get('locale')
        ]);
        Media::setDefault($user, 'defaults/images/user_avatar.png', 'avatar');
        
        if (Setting::get('requireEmailConfirmation')) {
            try {
                $user->sendEmailVerificationNotification();
                return response()->json(['message' => __('We have sent a verification email to the address you provided')], 201);
            } catch (\Exception $e) {
                $user->delete();
                return response()->json(['message' => __('Some error occurred while trying to send an email')], 400);
            }
        }
        return response()->json(null, 201);
    }
    
    /**
     * Logout the user.
     * @param $request
     * @return \Illuminate\Http\Response
     */
    public function logout()
    {
        $token = auth()->user()->token();
        $token->revoke();
        return response()->json(__('Logged out successfully.'), 200);
    }
}
