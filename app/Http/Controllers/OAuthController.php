<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\OIDCSetting;

class OAuthController extends Controller
{
    /**
     * Start OIDC redirect using Discovery + PKCE + state + nonce.
     */
    public function redirect(Request $request)
    {
        $cfg = OIDCSetting::get();
        $issuer = rtrim($cfg->issuer_url ?? '', '/');
        abort_unless($issuer, 400, 'OIDC issuer_url not configured');

        // 1) Discovery (cache 10 minutes)
        $disco = Cache::remember('oidc_disco', 600, function () use ($issuer) {
            $url = $issuer.'/.well-known/openid-configuration';
            $res = Http::timeout(10)->get($url)->throw();
            return $res->json();
        });
        $authorize = $disco['authorization_endpoint'] ?? null;
        abort_unless($authorize, 500, 'Missing authorization_endpoint in discovery');

        // 2) PKCE + state + nonce
        $code_verifier  = Str::random(64);
        $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
        $state = Str::random(32);
        $nonce = Str::random(32);

        Session::put('oidc_pkce_verifier', $code_verifier);
        Session::put('oidc_state', $state);
        Session::put('oidc_nonce', $nonce);

        $redirectUri = url($cfg->redirect_path ?: '/auth/oidc/callback');
        $scopes = $cfg->scopes ?: ['openid','profile','email'];

        $params = [
            'response_type'  => 'code',
            'client_id'      => $cfg->client_id,
            'redirect_uri'   => $redirectUri,
            'scope'          => implode(' ', $scopes),
            'state'          => $state,
            'nonce'          => $nonce,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256',
        ];

        // Optional mobile override: if a custom scheme is desired and whitelisted
        if ($request->has('mobile_redirect') && is_array($cfg->allowed_mobile_redirect_uris)) {
            $candidate = $request->string('mobile_redirect')->toString();
            if (in_array($candidate, $cfg->allowed_mobile_redirect_uris, true)) {
                $params['redirect_uri'] = $candidate;
            }
        }

        $qs = http_build_query($params);
        return redirect()->away($authorize.'?'.$qs);
    }

    /**
     * OIDC callback: exchange code for tokens, fetch userinfo, login.
     * NOTE: For brevity this checks state/nonce but does not perform JWT signature verification.
     * In production you should validate id_token against the JWKS (see TODO below).
     */
    public function callback(Request $request)
    {
        $cfg = OIDCSetting::get();
        $issuer = rtrim($cfg->issuer_url ?? '', '/');
        abort_unless($issuer, 400, 'OIDC issuer_url not configured');

        // Basic checks
        $code = $request->string('code')->toString();
        $state = $request->string('state')->toString();
        abort_unless($code !== '', 400, 'Missing authorization code');
        abort_unless($state === Session::pull('oidc_state'), 400, 'Invalid state');

        // Discovery
        $disco = Cache::remember('oidc_disco', 600, function () use ($issuer) {
            $url = $issuer.'/.well-known/openid-configuration';
            $res = Http::timeout(10)->get($url)->throw();
            return $res->json();
        });
        $tokenEndpoint   = $disco['token_endpoint'] ?? null;
        $userinfoEndpoint= $disco['userinfo_endpoint'] ?? null;
        abort_unless($tokenEndpoint, 500, 'Missing token_endpoint in discovery');

        // Token exchange with PKCE
        $code_verifier = Session::pull('oidc_pkce_verifier');
        $redirectUri = url($cfg->redirect_path ?: '/auth/oidc/callback');

        $form = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'client_id'     => $cfg->client_id,
            'code_verifier' => $code_verifier,
        ];
        // Some providers require client_secret for web apps
        if (!empty($cfg->client_secret)) {
            $form['client_secret'] = $cfg->client_secret;
        }

        $tokenRes = Http::asForm()->timeout(10)->post($tokenEndpoint, $form)->throw()->json();
        $accessToken = $tokenRes['access_token'] ?? null;
        $idToken     = $tokenRes['id_token']     ?? null;
        abort_unless($accessToken, 500, 'No access_token from token endpoint');

        // TODO (security best practice): verify id_token signature & claims using JWKS.
        // Validate nonce if present in id_token claims.
        if ($idToken) {
            $parts = explode('.', $idToken);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                $nonce = $payload['nonce'] ?? null;
                abort_unless($nonce === Session::pull('oidc_nonce'), 400, 'Invalid nonce');
            }
        }

        // Userinfo
        $email = null;
        $name  = null;
        if ($userinfoEndpoint) {
            $ui = Http::withToken($accessToken)->timeout(10)->get($userinfoEndpoint)->throw()->json();
            $email = $ui['email'] ?? null;
            $name  = $ui['name']  ?? ($ui['preferred_username'] ?? null);
        }

        // Find or create user
        $user = null;
        if ($email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name ?: 'User', 'password' => bcrypt(Str::random(32))]
            );
        } else {
            // fallback if no email claim
            $user = User::firstOrCreate(
                ['email' => 'oidc-'.Str::uuid().'@example.invalid'],
                ['name' => $name ?: 'User', 'password' => bcrypt(Str::random(32))]
            );
        }

        // Auto-register toggle: if disabled and user was newly created, abort (simple example)
        if ($user->wasRecentlyCreated && empty($cfg->auto_register)) {
            $user->delete();
            return redirect()->route('login')->with('error', 'Registration is disabled.');
        }

        Auth::login($user, true);
        return redirect()->intended('/');
    }
}
