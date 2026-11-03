<?php

namespace App\Http\Controllers\v1\Notification;

use App\Http\Controllers\Controller;
use App\Jobs\UserStat\UpdateLastReadNotificationJob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        UpdateLastReadNotificationJob::dispatch($request->user(), Carbon::now())
            ->onQueue('high');

        // Set user's notifications_count cache to 0
        Cache::put(
            str_replace('*', $request->user()->id, config('custom.cache.user_stat.pattern.notifications_count')),
            0,
            config('custom.cache.user_stat.ttl_duration')
        );

        return response()->json(null, 204);
    }
}
