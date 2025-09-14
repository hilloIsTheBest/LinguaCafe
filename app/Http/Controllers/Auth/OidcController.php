<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OidcController extends Controller
{
    protected function getGlobalSetting(string $name, $default = null)
    {
        $setting = Setting::where('user_id', -1)->where('name', $name)->first();
        if ($setting) {
            return json_decode($setting->value, true);
        }
        return $default;
    }

    protected function isEnabled(): bool
    {
        // Prefer DB setting, fall back to config/env
        $db = $this->getGlobalSetting('oidcEnabled', null);
        if ($db !== null) {
            return (bool) $db;
        }
        return (bool) config('oidc.enabled');
    }

    protected function makeClient(): \Jumbojett\OpenIDConnectClient
    {
        // Prefer DB overrides
        $issuer = $this->getGlobalSetting('oidcIssuer', config('oidc.issuer'));
        $clientId = $this->getGlobalSetting('oidcClientId', config('oidc.client_id'));
        $clientSecret = $this->getGlobalSetting('oidcClientSecret', config('oidc.client_secret'));
        $redirectUri = $this->getGlobalSetting('oidcRedirectUri', config('oidc.redirect_uri'));
        $scopesStr = $this->getGlobalSetting('oidcScopes', config('oidc.scopes', 'openid profile email'));
        $scopes = is_array($scopesStr) ? $scopesStr : preg_split('/\s+/', (string) $scopesStr);

        if (!$issuer || !$clientId || !$clientSecret) {
            abort(500, 'OIDC is not configured.');
        }

        $oidc = new \Jumbojett\OpenIDConnectClient($issuer, $clientId, $clientSecret);
        $oidc->setRedirectURL($redirectUri);
        $oidc->addScope($scopes);

        // Optional tuning
        $leeway = (int) ($this->getGlobalSetting('oidcLeeway', null) ?? config('oidc.leeway', 60));
        if (method_exists($oidc, 'setLeeway') && $leeway > 0) {
            $oidc->setLeeway($leeway);
        }

        $verifyTls = (bool) ($this->getGlobalSetting('oidcVerifyTls', null) ?? config('oidc.verify_tls', true));
        if (method_exists($oidc, 'setVerifyPeer')) {
            $oidc->setVerifyPeer($verifyTls);
        }

        // Optional manual endpoint overrides
        $authzUrl = $this->getGlobalSetting('oidcAuthorizeUrl', null);
        $userinfoUrl = $this->getGlobalSetting('oidcUserinfoUrl', null);
        $jwksUrl = $this->getGlobalSetting('oidcJwksUrl', null);
        if (method_exists($oidc, 'providerConfigParam')) {
            $params = [];
            if (!empty($authzUrl)) $params['authorization_endpoint'] = $authzUrl;
            if (!empty($userinfoUrl)) $params['userinfo_endpoint'] = $userinfoUrl;
            if (!empty($jwksUrl)) $params['jwks_uri'] = $jwksUrl;
            if (!empty($params)) {
                // Call once per param to stay compatible
                foreach ($params as $k => $v) {
                    $oidc->providerConfigParam([$k => $v]);
                }
            }
        }

        return $oidc;
    }

    // Kick off the OIDC flow (or complete it if returning from the provider)
    public function redirect(Request $request)
    {
        if (!$this->isEnabled()) {
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
        if (!$this->isEnabled()) {
            abort(404);
        }

        $oidc = $this->makeClient();
        $oidc->authenticate();

        return $this->finalizeLogin($request, $oidc);
    }

    public function logout(Request $request)
    {
        // Local logout
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Optional RP-initiated logout
        $logoutUrl = (string) ($this->getGlobalSetting('oidcLogoutUrl', '') ?: '');
        if ($logoutUrl !== '') {
            return redirect()->away($logoutUrl);
        }

        return redirect('/login');
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

        // Find (or create if auto-register enabled) the local user
        $user = User::where('email', $email)->first();

        $autoRegister = $this->getGlobalSetting('oidcAutoRegister', true) !== false; // default on
        if (!$user) {
            if (!$autoRegister) {
                abort(403, 'OIDC: account not registered.');
            }
            $userCount = User::count();
            $isAdmin = $userCount === 0; // first local user becomes admin (existing app behavior)
            $password = Str::random(40); // not used for OIDC users

            /** @var UserService $userService */
            $userService = app(UserService::class);
            $userService->createUser($name ?: ($email), $email, $password, $isAdmin, true);

            $user = User::where('email', $email)->first();
        }

        // Optional claims-based admin mapping
        try {
            $groupClaimName = (string) ($this->getGlobalSetting('oidcGroupClaim', '') ?: '');
            $permClaimName = (string) ($this->getGlobalSetting('oidcPermissionClaim', '') ?: '');
            $adminGroup = (string) ($this->getGlobalSetting('oidcAdminGroup', '') ?: '');
            $adminPerm = (string) ($this->getGlobalSetting('oidcAdminPermission', '') ?: '');

            $allClaims = [];
            if (method_exists($oidc, 'getVerifiedClaims')) {
                $v = $oidc->getVerifiedClaims();
                if (is_array($v)) $allClaims = $v;
            }
            if (!$allClaims && method_exists($oidc, 'getAccessTokenPayload')) {
                $p = $oidc->getAccessTokenPayload();
                if (is_array($p)) $allClaims = array_merge($allClaims, $p);
            }
            // Try userinfo for claims as well
            if (!$allClaims && method_exists($oidc, 'requestUserInfo')) {
                // no-op here unless needed
            }

            $makeAdmin = false;
            if ($adminGroup && $groupClaimName && isset($allClaims[$groupClaimName])) {
                $val = $allClaims[$groupClaimName];
                if (is_string($val)) $makeAdmin = str_contains($val, $adminGroup);
                if (is_array($val)) $makeAdmin = in_array($adminGroup, $val, true);
            }
            if (!$makeAdmin && $adminPerm && $permClaimName && isset($allClaims[$permClaimName])) {
                $val = $allClaims[$permClaimName];
                if (is_string($val)) $makeAdmin = str_contains($val, $adminPerm);
                if (is_array($val)) $makeAdmin = in_array($adminPerm, $val, true);
            }
            if ($makeAdmin && !$user->is_admin) {
                $user->is_admin = true;
                $user->save();
            }
        } catch (\Throwable $e) {
            // ignore mapping errors to avoid blocking login
        }

        // Log the user into the Laravel session
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect('/');
    }
}

