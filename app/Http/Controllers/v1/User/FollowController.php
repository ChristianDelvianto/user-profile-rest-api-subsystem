<?php

namespace App\Http\Controllers\v1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\v1\User\FollowRequest;
use App\Jobs\Notification\User\CreateAccountFollowerTypeOrAttachNewUserJob;
use App\Jobs\UserStat\UpdateFollowersCountCacheJob;
use App\Jobs\UserStat\UpdateFollowingCountCacheJob;
use App\Models\User;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FollowController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(FollowRequest $request, User $user): JsonResponse
    {
        // O(1) - unique index
        $isFollowing = $user->followers()->wherePivot('follower_id', $request->user()->id)->exists();

        if ($isFollowing) {
            return response()->json(null, 204);
        }

        try {
            DB::transaction(function () use ($request, $user): void {
                // If $request->user() already followed $user, this line below will throw duplicate key violation
                $user->followers()->attach($request->user()->id);

                UpdateFollowersCountCacheJob::dispatch($user->id, 1)->onQueue('high')->afterCommit();
                UpdateFollowingCountCacheJob::dispatch($request->user()->id, 1)->onQueue('high')->afterCommit();

                CreateAccountFollowerTypeOrAttachNewUserJob::dispatch($request->user(), $user)->onQueue('high')->afterCommit();
            }, attempts: config('custom.database.transaction.attempts'));

            return response()->json(null, 204);
        } catch (QueryException $e) {
            report($e);

            // Duplicate key violation, $request->user() alredy followed $user
            if ($e->getCode() === '23505' && str_contains($e->errorInfo[2] ?? '', 'user_follow')) {
                return response()->json(null, 204);
            }

            throw $e;
        } catch (Exception $e) {
            report($e);

            throw $e;
        }
    }
}
