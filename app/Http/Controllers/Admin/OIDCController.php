<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OIDCSetting;

class OIDCController extends Controller
{
    public function edit()
    {
        $oidc = OIDCSetting::get();
        return view('admin.settings', compact('oidc'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'issuer_url'                => 'nullable|url',
            'authorize_url'             => 'nullable|url',
            'userinfo_url'              => 'nullable|url',
            'jwks_url'                  => 'nullable|url',
            'logout_url'                => 'nullable|url',
            'allowed_mobile_redirect_uris' => 'nullable|string',
            'button_text'               => 'required|string|max:255',
            'button_icon'               => 'required|string|max:255',
            'scopes'                    => 'nullable|string',
            'auto_launch'               => 'boolean',
            'auto_register'             => 'boolean',
            'group_claim'               => 'nullable|string',
            'permission_claim'          => 'nullable|string',
            'ldap_enabled'              => 'boolean',
            'client_id'                => 'nullable|string',
            'client_secret'            => 'nullable|string',
            'redirect_path'           => 'nullable|string',
        ]);

        $oidc = OIDCSetting::get();

        // Convert comma separated strings to arrays
        if ($validated['allowed_mobile_redirect_uris']) {
            $validated['allowed_mobile_redirect_uris'] = array_map('trim', explode(',', $validated['allowed_mobile_redirect_uris']));
        }
        if ($validated['scopes']) {
            $validated['scopes'] = array_map('trim', explode(',', $validated['scopes']));
        }

        $oidc->updateFromArray($validated);

        return redirect()->route('admin.oidc.edit')->with('status', 'OIDC settings updated successfully.');
    }

    // Lightweight diagnostics to verify server-side OIDC config and discovery
    public function health(\Illuminate\Http\Request $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        // Pull settings from global settings store (not from OIDCSetting model)
        $get = function(string $name, $default = null) {
            $s = \App\Models\Setting::where('user_id', -1)->where('name', $name)->first();
            return $s ? json_decode($s->value, true) : $default;
        };

        $issuer = (string) $get('oidcIssuer', '');
        $clientId = (string) $get('oidcClientId', '');
        $redirect = (string) $get('oidcRedirectUri', '');
        $enabled = (bool) $get('oidcEnabled', false);

        $discovery = null; $error = null;
        if ($issuer) {
            try {
                $url = rtrim($issuer, '/').'/.well-known/openid-configuration';
                $res = \Illuminate\Support\Facades\Http::timeout(10)->get($url);
                if ($res->ok()) {
                    $discovery = $res->json();
                } else {
                    $error = 'Discovery HTTP '.$res->status();
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = 'Missing issuer';
        }

        return response()->json([
            'enabled' => $enabled,
            'issuer' => $issuer,
            'clientId' => $clientId !== '' ? '[set]' : '[missing]',
            'redirectUri' => $redirect,
            'expectedCallback' => url('/auth/oidc/callback'),
            'redirectMatchesExpected' => $redirect === url('/auth/oidc/callback'),
            'discovery' => $discovery ? [
                'authorization_endpoint' => $discovery['authorization_endpoint'] ?? null,
                'token_endpoint' => $discovery['token_endpoint'] ?? null,
                'userinfo_endpoint' => $discovery['userinfo_endpoint'] ?? null,
                'jwks_uri' => $discovery['jwks_uri'] ?? null,
                'issuer' => $discovery['issuer'] ?? null,
            ] : null,
            'error' => $error,
        ], 200);
    }
}
