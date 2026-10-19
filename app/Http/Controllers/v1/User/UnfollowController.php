<?php

namespace App\Http\Controllers\v1\User;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\v1\User\UnfollowRequest;
use App\Models\Notification;
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
        // First guard
        $isFollowing = $user->followers()->wherePivot('follower_id', $request->user()->id)->exists();

        if ($isFollowing) {
            try {
                DB::transaction(function () use ($request, $user) {
                    // 0 means no record; $request->user() not following $user
                    if (0 === $user->followers()->detach($request->user()->id)) {
                        return;
                    }

                    $notification = Notification::query()
                                    ->select('notifications.*')
                                    ->join('notification_user', 'notifications.id', '=', 'notification_user.notification_id')
                                    ->where('notifications.user_id', $user->id)
                                    ->where('notifications.type', NotificationType::AccountFollower)
                                    ->where('notification_user.user_id', $request->user()->id)
                                    ->lock('for update of notifications')
                                    ->first();

                    if ($notification && 1 === $notification->users()->detach($request->user()->id)) {
                        $notification->decrement('groups_count');
                    }
                });
            } catch (Exception $e) {
                report($e);

                throw $e;
            }
        }

        return response()->json(null, 204);
    }
}
