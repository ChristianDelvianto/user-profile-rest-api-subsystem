<?php

namespace App\Http\Controllers\v1\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\v1\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserStat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IndexController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $pagination = Notification::query()
                    ->select(
                        'notifications.*',
                        DB::raw('MAX(notification_user.id) AS last_activity_id'),
                        DB::raw('MAX(notification_user.created_at) AS last_activity_at')
                    )
                    ->leftJoin('notification_user', 'notifications.id', '=', 'notification_user.notification_id')
                    ->with([
                        'users' => function ($query) {
                            $query->select(
                                'users.id',
                                'users.username',
                                'users.name',
                                'notification_user.id AS activity_id',
                                'notification_user.created_at AS activity_created_at'
                            )
                            ->latest('notification_user.created_at')
                            ->limit(5);
                        }
                    ])
                    ->where('notifications.user_id', $request->user()->id)
                    ->orderBy('last_activity_at', 'DESC')
                    ->orderBy('last_activity_id', 'DESC')
                    ->groupBy('notifications.id', 'notification_user.notification_id')
                    ->cursorPaginate();

        if ($pagination->isNotEmpty()) {
            /**
             * Only when the user has notifications, then we will fetch the user stats and set the 'is_read' attribute for each notification.
             * This is to avoid unnecessary queries when the user has no notifications.
             */
            $this->setReadAttribute($request->user(), $pagination->getCollection());

            $this->setGroupsCountValue($pagination->getCollection());
        }

        return response()->json([
            'has_more' => $pagination->hasMorePages(),
            'cursor' => optional($pagination->nextCursor())?->encode(),
            'items' => NotificationResource::collection($pagination->getCollection())
        ]);
    }

    /**
     * Get notifications 'groups_count' cache and set the value.
     */
    protected function setGroupsCountValue(Collection $notifications): void
    {
        $cacheKeys = [];

        foreach ($notifications as $notification) {
            $groupsCountCacheKey = str_replace('*', $notification->uuid, config('custom.cache.notification.pattern.groups_count'));

            array_push($cacheKeys, $groupsCountCacheKey);
        }

        $cache = Cache::many($cacheKeys);

        foreach ($notifications as $notification) {
            $groupsCountCacheKey = str_replace('*', $notification->uuid, config('custom.cache.notification.pattern.groups_count'));

            $groupsCountCache = $cache[$groupsCountCacheKey];

            if (isset($groupsCountCache)) {
                $notification->setAttribute('groups_count', (int) $groupsCountCache);
            }
        }
    }

    /**
     * Set notifications 'is_read' attribute and the value comes from comparing user's user_stats `last_read_notification_at` with notification's `last_activity_at`.
     */
    protected function setReadAttribute(User $user, Collection $notifications): void
    {
        $userStat = UserStat::where('user_id', $user->id)->firstOrFail();

        $lastReadNotificationAt = Carbon::parse($userStat->last_read_notification_at);

        foreach ($notifications as $notification) {
            $notification->setAttribute('is_read', $lastReadNotificationAt->gt($notification->last_activity_at));
        }
    }
}
