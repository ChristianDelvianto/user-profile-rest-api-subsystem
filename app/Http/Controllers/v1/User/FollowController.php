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

class FollowController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(FollowRequest $request, User $user): JsonResponse
    {
        try {
            // If $request->user() already followed $user, this line below will throw unique violation
            $user->followers()->attachOrFail($request->user()->id);

            UpdateFollowersCountCacheJob::dispatch(user: $user, count: 1)->onQueue('high');

            UpdateFollowingCountCacheJob::dispatch(user: $request->user(), count: 1)->onQueue('high');

            CreateAccountFollowerTypeOrAttachNewUserJob::dispatch(follower: $request->user(), user: $user)->onQueue('high');

            return response()->json(null, 204);
        } catch (QueryException $e) {
            report($e);

            // Duplicate key violation, $request->user() already following $user
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
