<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Models\User;
use App\Models\OIDCSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Controller; // Added missing import

class OAuthController extends Controller
{
    public function callback(Request $request)
    {
        $oidc = OIDCSetting::get();

        if (!$request->has('code')) {
            return redirect()->route('login')->with('error', 'Missing authorization code.');
        }

        // Exchange code for token
        $client = new Client();
        $response = $client->post($oidc->jwks_url, [
            'form_params' => [
                'grant_type'   => 'authorization_code',
                'code'         => $request->input('code'),
                'redirect_uri' => url('/oauth/callback'),
                'client_id'    => config('services.oidc.client_id'),
                'client_secret'=> config('services.oidc.client_secret'),
            ],
        ]);

        $tokenData = json_decode((string) $response->getBody(), true);

        // Verify token (simplified; real implementation should verify signature)
        if (!isset($tokenData['access_token'])) {
            return redirect()->route('login')->with('error', 'Failed to obtain access token.');
        }

        // Fetch userinfo
        $userinfoResponse = $client->get($oidc->userinfo_url, [
            'headers' => ['Authorization' => 'Bearer '.$tokenData['access_token']],
        ]);

        $userInfo = json_decode((string) $userinfoResponse->getBody(), true);

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $userInfo['email'] ?? null],
            [
                'name'  => $userInfo['name'] ?? '',
                'password' => bcrypt(str_random(16)),
            ]
        );

        // Store claims if needed
        Session::put('oidc_claims', $userInfo);

        Auth::login($user, true);

        return redirect()->intended('/');
    }
}
