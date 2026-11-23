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
             * 
             * This is to avoid unnecessary queries when the user has no notifications.
             */
            $this->setIsReadAttribute(user: $request->user(), notifications: $pagination->getCollection());

            $this->setGroupsCountValue(notifications: $pagination->getCollection());
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
        $groupsCountCacheKeyPattern = config('custom.cache.notification.pattern.groups_count');

        $cacheKeys = [];

        foreach ($notifications as $notification) {
            $groupsCountCacheKey = str_replace('*', $notification->uuid, $groupsCountCacheKeyPattern);

            array_push($cacheKeys, $groupsCountCacheKey);
        }

        /**
         * Why use Cache::many() instead of fetching cache entries one by one?
         *
         * Fetching each notification groups_count cache individually can create unnecessary load on Redis.
         * Imagine 10,000 users accessing their notifications simultaneously, with
         * 20 notifications per page.
         *
         * The calculation would be:
         * 10,000 users × 20 notifications = 200,000 individual Redis requests.
         *
         * Using Cache::many(), we batch the 20 cache keys into a single request
         * per user, reducing the total from 200,000 to approximately 10,000 Redis
         * requests (95% fewer requests).
         *
         * This reduces network round trips and Redis request overhead, helping
         * improve efficiency under concurrent user traffic.
         */
        $cache = Cache::many(keys: $cacheKeys);

        foreach ($notifications as $notification) {
            $groupsCountCacheKey = str_replace('*', $notification->uuid, $groupsCountCacheKeyPattern);

            $groupsCountCache = $cache[$groupsCountCacheKey];

            if (isset($groupsCountCache)) {
                $notification->setAttribute(key: 'groups_count', value: (int) $groupsCountCache);
            }
        }
    }

    /**
     * Set notifications `is_read` attribute and the value comes from comparing user's user_stats `last_read_notification_at` with notification's `last_activity_at`.
     * 
     * Tips: If you use VSCode, hover to the function name to read the function description easier. :P (HAHA !)
     */
    protected function setIsReadAttribute(User $user, Collection $notifications): void
    {
        $userStat = UserStat::where('user_id', $user->id)->firstOrFail();

        $lastReadNotificationAt = Carbon::parse($userStat->last_read_notification_at);

        foreach ($notifications as $notification) {
            // When $lastReadNotificationAt greater than $notification last_activity_at, we will set `is_read` value to true
            $notification->setAttribute(key: 'is_read', value: $lastReadNotificationAt->gt($notification->last_activity_at));
        }
    }
}
