<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\PushSubscription;

class PushController extends Controller
{
    public function config()
    {
        // Read or generate VAPID keys from settings
        $get = function(string $name, $default=null) {
            $s = \App\Models\Setting::where('user_id', -1)->where('name', $name)->first();
            return $s ? json_decode($s->value, true) : $default;
        };
        $set = function(string $name, $value) {
            $s = \App\Models\Setting::firstOrNew(['user_id' => -1, 'name' => $name]);
            $s->value = json_encode($value);
            $s->save();
        };

        $pub = $get('vapidPublicKey');
        $priv = $get('vapidPrivateKey');
        if (!$pub || !$priv) {
            try {
                $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
                $pub = $keys['publicKey'];
                $priv = $keys['privateKey'];
                $set('vapidPublicKey', $pub);
                $set('vapidPrivateKey', $priv);
            } catch (\Throwable $e) {
                Log::warning('VAPID key generation failed', ['e' => $e->getMessage()]);
            }
        }
        return response()->json(['publicKey' => $pub, 'subject' => url('/')]);
    }

    public function subscribe(Request $request)
    {
        $payload = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);
        $ua = (string) $request->header('User-Agent');
        $sub = PushSubscription::updateOrCreate(
            ['endpoint' => $payload['endpoint']],
            [
                'user_id' => Auth::id(),
                'p256dh' => $payload['keys']['p256dh'],
                'auth'   => $payload['keys']['auth'],
                'ua'     => $ua,
            ]
        );
        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $endpoint = $request->string('endpoint')->toString();
        if ($endpoint) {
            PushSubscription::where('endpoint', $endpoint)->delete();
        }
        return response()->json(['ok' => true]);
    }

    public function test(Request $request)
    {
        // Send a test notification to current user (admin only)
        $u = Auth::user();
        if (!$u || !$u->is_admin) abort(403);

        $subs = PushSubscription::where('user_id', $u->id)->get();
        if ($subs->isEmpty()) return response()->json(['sent' => 0]);

        $get = function(string $name, $default=null) {
            $s = \App\Models\Setting::where('user_id', -1)->where('name', $name)->first();
            return $s ? json_decode($s->value, true) : $default;
        };
        $pub = $get('vapidPublicKey');
        $priv = $get('vapidPrivateKey');
        $auth = ['VAPID' => ['subject' => url('/'), 'publicKey' => $pub, 'privateKey' => $priv]];

        $webPush = new \Minishlink\WebPush\WebPush($auth);
        $count = 0;
        foreach ($subs as $s) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $s->endpoint,
                'publicKey' => $s->p256dh,
                'authToken' => $s->auth,
                'contentEncoding' => 'aes128gcm',
            ]);
            $webPush->queueNotification($subscription, json_encode(['title' => 'LinguaCafe', 'body' => 'Test notification']));
            $count++;
        }
        foreach ($webPush->flush() as $report) {
            // ignore individual reports
        }
        return response()->json(['sent' => $count]);
    }
}

