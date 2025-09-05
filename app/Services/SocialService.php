<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPurchase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SocialService {
    public function handleLogin(int $userId): void {
        $user = User::find($userId);
        if (!$user) return;

        $today = Carbon::now()->toDateString();
        $yesterday = Carbon::now()->subDay()->toDateString();

        // update streak
        if ($user->last_login_date === $today) {
            // already counted today
        } else if ($user->last_login_date === $yesterday) {
            $user->streak_count = ($user->streak_count ?? 0) + 1;
        } else {
            $user->streak_count = 1;
        }
        if ($user->streak_count > ($user->best_streak ?? 0)) {
            $user->best_streak = $user->streak_count;
        }
        $user->last_login_date = $today;

        // award daily login coins
        $dailyCoins = config('linguacafe.social.daily_login_coins', 5);
        $this->awardCoinsInternal($user, $dailyCoins);
        $user->save();
    }

    public function awardCoins(int $userId, int $amount): void {
        $user = User::find($userId);
        if (!$user) return;
        $this->awardCoinsInternal($user, $amount);
        $user->save();
    }

    private function awardCoinsInternal(User $user, int $amount): void {
        $user->coins = max(0, ($user->coins ?? 0) + $amount);
    }

    public function purchaseItem(int $userId, string $itemKey, int $price): bool {
        return DB::transaction(function() use($userId, $itemKey, $price) {
            $user = User::lockForUpdate()->find($userId);
            if (!$user) return false;
            if (($user->coins ?? 0) < $price) return false;

            $existing = UserPurchase
                ::where('user_id', $userId)
                ->where('item_key', $itemKey)
                ->first();
            if ($existing) return true; // already owned

            $user->coins = $user->coins - $price;
            $user->save();

            $purchase = new UserPurchase();
            $purchase->user_id = $userId;
            $purchase->item_key = $itemKey;
            $purchase->save();
            return true;
        });
    }

    public function getLeaderboard(int $limit = 10) {
        $topCoins = User::select('id','name','coins')->orderByDesc('coins')->limit($limit)->get();
        $topStreaks = User::select('id','name','streak_count','best_streak')->orderByDesc('streak_count')->limit($limit)->get();
        return (object) [ 'coins' => $topCoins, 'streaks' => $topStreaks ];
    }
}

