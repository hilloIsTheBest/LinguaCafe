<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OidcController extends Controller
{
    protected function makeClient(): \Jumbojett\OpenIDConnectClient
    {
        $issuer = config('oidc.issuer');
        $clientId = config('oidc.client_id');
        $clientSecret = config('oidc.client_secret');
        $redirectUri = config('oidc.redirect_uri');
        $scopes = preg_split('/\s+/', (string) config('oidc.scopes', 'openid profile email'));

        if (!$issuer || !$clientId || !$clientSecret) {
            abort(500, 'OIDC is not configured.');
        }

        $oidc = new \Jumbojett\OpenIDConnectClient($issuer, $clientId, $clientSecret);
        $oidc->setRedirectURL($redirectUri);
        $oidc->addScope($scopes);

        // Optional tuning
        $leeway = (int) config('oidc.leeway', 60);
        if (method_exists($oidc, 'setLeeway') && $leeway > 0) {
            $oidc->setLeeway($leeway);
        }

        $verifyTls = (bool) config('oidc.verify_tls', true);
        if (method_exists($oidc, 'setVerifyPeer')) {
            $oidc->setVerifyPeer($verifyTls);
        }

        return $oidc;
    }

    // Kick off the OIDC flow (or complete it if returning from the provider)
    public function redirect(Request $request)
    {
        if (!config('oidc.enabled')) {
            abort(404);
        }

        $oidc = $this->makeClient();
        // The library will redirect on first call; on callback it will finish the code flow.
        $oidc->authenticate();

        // If we are here after callback, proceed with local login.
        return $this->finalizeLogin($request, $oidc);
    }

    // Explicit callback endpoint if you prefer distinct routes
    public function callback(Request $request)
    {
        if (!config('oidc.enabled')) {
            abort(404);
        }

        $oidc = $this->makeClient();
        $oidc->authenticate();

        return $this->finalizeLogin($request, $oidc);
    }

    protected function finalizeLogin(Request $request, \Jumbojett\OpenIDConnectClient $oidc)
    {
        $email = null;
        $name = null;
        $sub = null;

        // Prefer verified ID token claims if available
        $claims = null;
        if (method_exists($oidc, 'getVerifiedClaims')) {
            $claims = $oidc->getVerifiedClaims();
        }

        if (is_array($claims)) {
            $email = $claims['email'] ?? null;
            $name = $claims['name'] ?? ($claims['given_name'] ?? ($claims['preferred_username'] ?? null));
            $sub = $claims['sub'] ?? null;
        } else {
            // Fallback to userinfo endpoint
            if (method_exists($oidc, 'requestUserInfo')) {
                $email = $oidc->requestUserInfo('email');
                $name = $oidc->requestUserInfo('name') ?? $oidc->requestUserInfo('preferred_username');
                $sub = $oidc->requestUserInfo('sub');
            }
        }

        if (!$email) {
            abort(422, 'OIDC: email claim is required but missing.');
        }

        // Find or create the local user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $userCount = User::count();
            $isAdmin = $userCount === 0; // first local user becomes admin (existing app behavior)
            $password = Str::random(40); // not used for OIDC users

            /** @var UserService $userService */
            $userService = app(UserService::class);
            $userService->createUser($name ?: ($email), $email, $password, $isAdmin, true);

            $user = User::where('email', $email)->first();
        }

        // Log the user into the Laravel session
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect('/');
    }
}

