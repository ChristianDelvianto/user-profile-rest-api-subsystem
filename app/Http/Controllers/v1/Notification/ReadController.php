<?php

namespace App\Http\Controllers\v1\Notification;

use App\Http\Controllers\Controller;
use App\Jobs\UserStat\UpdateLastReadNotificationJob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        UpdateLastReadNotificationJob::dispatch($request->user(), Carbon::now())
            ->onQueue('high');

        return response()->json(null, 204);
    }
}
