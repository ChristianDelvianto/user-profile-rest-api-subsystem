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

class UnfollowController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UnfollowRequest $request, User $user): JsonResponse
    {
        try {
            // 0 means no record deleted; $request->user() not following $user
            if (1 === $user->followers()->detachOrFail($request->user()->id)) {
                UpdateFollowersCountCacheJob::dispatch(user: $user, count: -1)->onQueue('high');

                UpdateFollowingCountCacheJob::dispatch(user: $request->user(), count: -1)->onQueue('high');

                AccountFollowerTypeDetachUserJob::dispatch(follower: $request->user(), user: $user)->onQueue('high');
            }

            return response()->json(null, 204);
        } catch (Exception $e) {
            report($e);

            throw $e;
        }
    }
}
