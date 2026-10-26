<?php

namespace App\Http\Controllers\v1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\v1\User\UnfollowRequest;
use App\Jobs\Notification\User\AccountFollowerTypeDetachUserJob;
use App\Jobs\UserStat\UpdateFollowersCountCacheJob;
use App\Jobs\UserStat\UpdateFollowingCountCacheJob;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnfollowController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UnfollowRequest $request, User $user): JsonResponse
    {
        // O(1) - unique index
        $isFollowing = $user->followers()->wherePivot('follower_id', $request->user()->id)->exists();

        if (! $isFollowing) {
            return response()->json(null, 204);
        }

        try {
            DB::transaction(function () use ($request, $user): void {
                // 0 means no record deleted; $request->user() not following $user
                if (0 === $user->followers()->detach($request->user()->id)) {
                    return;
                }

                UpdateFollowersCountCacheJob::dispatch($user->id, -1)->onQueue('high')->afterCommit();
                UpdateFollowingCountCacheJob::dispatch($request->user()->id, -1)->onQueue('high')->afterCommit();

                AccountFollowerTypeDetachUserJob::dispatch($request->user(), $user)->onQueue('high')->afterCommit();
            }, attempts: config('custom.database.transaction.attempts'));

            return response()->json(null, 204);
        } catch (Exception $e) {
            report($e);

            throw $e;
        }
    }
}
