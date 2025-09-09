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
}
