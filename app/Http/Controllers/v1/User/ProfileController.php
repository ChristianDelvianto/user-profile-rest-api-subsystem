<?php

namespace App\Http\Controllers\v1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\v1\UserResource;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProfileController extends Controller
{
    protected int $followersCount = 0;
    protected int $followingCount = 0;
    protected bool $isFollowed = false;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, User $user): JsonResponse
    {
        $userStats = UserStat::where('user_id', $user->id)->firstOrFail();

        $followersCountCacheKey = str_replace('*', $user->id, config('custom.cache.user_stat.pattern.followers_count'));
        $followingCountCacheKey = str_replace('*', $user->id, config('custom.cache.user_stat.pattern.following_count'));

        $cache = Cache::many([$followersCountCacheKey, $followingCountCacheKey]);

        // Fallback to UserStat
        $this->followersCount = $cache[$followersCountCacheKey] ?? $userStats->followers_count;
        $this->followingCount = $cache[$followingCountCacheKey] ?? $userStats->following_count;

        if ($request->user() && $request->user()->id !== $user->id) {
            $this->isFollowed = $user->followers()
                                    ->wherePivot('follower_id', $request->user()->id)
                                    ->exists();
        }

        // Fallback when $request->user() is following $user but $followersCount has value 0
        if ($request->user() && $this->isFollowed && (int) $this->followersCount === 0) {
            $this->followersCount = 1;
        }

        return response()->json([
            'followers_count' => (int) $this->followersCount, // Convert to integer, value could come from cache (String-based)
            'following_count' => (int) $this->followingCount, // Convert to integer, value could come from cache (String-based)
            'is_followed' => $this->isFollowed,
            'profile' => UserResource::make($user)
        ]);
    }
}
