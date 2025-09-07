<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Services\GoalService;
use App\Services\UserService;
use App\Services\Auth\LdapService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// request classes
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UpdatePasswordRequest;
use App\Http\Requests\Users\AuthenticateUserRequest;

class UserController extends Controller {
    private $userService;

    public function __construct(UserService $userService) {
        $this->userService = $userService;

    }

    public function isUserPasswordChanged() 
    {
        $passwordChanged = Auth::user()->password_changed;
        return response()->json($passwordChanged, 200);
    }

    public function getUsers() 
    {
        $userId = Auth::user()->id;

        try {
            $users = $this->userService->getUsers($userId);
        } catch(\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->json($users, 200);
    }

    public function showLoginForm() 
    {
        $userCount = User::count();
        $theme = $_COOKIE['theme'] ?? 'light';

        if (Auth::check()) {
            return redirect()->intended('/');
        }

        // Load OIDC settings from DB (fallback to config)
        $get = function(string $name, $default = null) {
            $s = Setting::where('user_id', -1)->where('name', $name)->first();
            return $s ? json_decode($s->value, true) : $default;
        };

        $oidcEnabled = $get('oidcEnabled', config('oidc.enabled')) ? true : false;
        $oidcButtonText = $get('oidcButtonText', 'Login with SSO');
        $oidcButtonIcon = $get('oidcButtonIcon', 'mdi-shield-account');
        $oidcAutoLaunch = $get('oidcAutoLaunch', false) ? true : false;

        return view('auth.login', [
            'userCount' => $userCount,
            'userUuid' => '',
            'theme' => $theme,
            'oidcEnabled' => $oidcEnabled,
            'oidcButtonText' => $oidcButtonText,
            'oidcButtonIcon' => $oidcButtonIcon,
            'oidcAutoLaunch' => $oidcAutoLaunch,
        ]);
    }
    
    public function authenticateUser(AuthenticateUserRequest $request) 
    {
        $email = $request->post('email');
        $password = $request->post('password');

        if (Auth::attempt([
            'email' => $email,
            'password' => $password,
        ])) {
            $request->session()->regenerate();
            Auth::logoutOtherDevices($password);
            try {
                (new \App\Services\SocialService())->handleLogin(Auth::id());
            } catch (\Throwable $e) {}

            return response()->json('User has been logged in successfully.', 200);
        }

        // Local auth failed; try LDAP if enabled
        try {
            $ldap = app(LdapService::class);
            if ($ldap->isEnabled()) {
                $result = $ldap->authenticate($email, $password);
                if ($result) {
                    [$ldapEmail, $ldapName] = $result;
                    $user = User::where('email', $ldapEmail)->first();
                    $get = function(string $name, $default = null) {
                        $s = \App\Models\Setting::where('user_id', -1)->where('name', $name)->first();
                        return $s ? json_decode($s->value, true) : $default;
                    };
                    $autoRegister = $get('ldapAutoRegister', true) !== false;
                    if (!$user && $autoRegister) {
                        $isAdmin = User::count() === 0;
                        $randPass = Str::random(40);
                        /** @var UserService $userService */
                        $userService = app(UserService::class);
                        $userService->createUser($ldapName ?: $ldapEmail, $ldapEmail, $randPass, $isAdmin, true);
                        $user = User::where('email', $ldapEmail)->first();
                    }
                    if ($user) {
                        Auth::login($user, true);
                        $request->session()->regenerate();
                        try { (new \App\Services\SocialService())->handleLogin(Auth::id()); } catch (\Throwable $e) {}
                        return response()->json('User has been logged in successfully.', 200);
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall through to generic error
        }

        return response()->json('Login error.', 500);
    }

    public function updatePassword(UpdatePasswordRequest $request) 
    {
        $user = Auth::user();
        $password = $request->post('password');

        try {
            $this->userService->updatePassword($user, $password);
        } catch(\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->json('Password has been updated successfully.', 200);
    }

    public function createUser(CreateUserRequest $request) 
    {
        $userCount = User::count();
        $name = $request->post('name');
        $email = $request->post('email');
        $password = $request->post('password');
        $isAdmin = $request->post('isAdmin');
        $savedWordsLimit = $request->post('savedWordsLimit');
        $passwordChanged = $userCount === 0;

        // If this is the first user, it can be created without any authorization.
        if (!Auth::check() && $userCount !== 0) {
            abort(401, 'Not authorized to create a user.');
        }

        try {
            $this->userService->createUser($name, $email, $password, $isAdmin, $passwordChanged, $savedWordsLimit ?? 0);
        } catch(\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->json('User has been created successfully.', 200);
    }

    public function updateUser(UpdateUserRequest $request) 
    {
        $userId = $request->post('userId');
        $name = $request->post('name');
        $email = $request->post('email');
        $isAdmin = $request->post('isAdmin');
        $savedWordsLimit = $request->post('savedWordsLimit');

        try {
            $this->userService->updateUser($userId, $name, $email, $isAdmin, $savedWordsLimit);
        } catch(\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->json('User has been updated successfully.', 200);
    }

    public function deleteUserLanguageData($language) 
    {
        $userId = Auth::user()->id;

        try {
            $this->userService->deleteUserLanguageData($userId, $language);
        } catch(\Exception $e) {
            abort(500, $e->getMessage());
        }
        
        return response()->json('User has been deleted successfully.', 200);
    }
}
