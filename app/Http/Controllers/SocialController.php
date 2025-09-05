<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Services\SocialService;
use App\Models\User;
use App\Models\UserPurchase;

class SocialController extends Controller
{
    private SocialService $socialService;

    public function __construct(SocialService $socialService)
    {
        $this->socialService = $socialService;
    }

    public function getStats()
    {
        $user = Auth::user();
        $savedWords = \App\Models\EncounteredWord::where('user_id', $user->id)->where('stage', '<', 0)->count();
        $purchases = UserPurchase
            ::where('user_id', $user->id)
            ->pluck('item_key')
            ->toArray();
        return response()->json([
            'coins' => $user->coins ?? 0,
            'streak' => $user->streak_count ?? 0,
            'bestStreak' => $user->best_streak ?? 0,
            'savedWords' => $savedWords,
            'savedWordsLimit' => $user->saved_words_limit ?? 0,
            'purchases' => $purchases,
        ], 200);
    }

    public function purchase(Request $request)
    {
        $request->validate([
            'itemKey' => 'required|string|max:128',
            'price' => 'required|integer|min:0',
        ]);

        $userId = Auth::user()->id;
        $ok = $this->socialService->purchaseItem($userId, $request->itemKey, intval($request->price));
        if (!$ok) abort(400, 'Insufficient coins or request invalid.');
        return response()->json('Purchase successful', 200);
    }

    public function leaderboard()
    {
        $data = $this->socialService->getLeaderboard(10);
        return response()->json($data, 200);
    }

    public function getShop()
    {
        $shop = config('linguacafe.social.shop', []);
        return response()->json($shop, 200);
    }
}
