<?php

namespace App\Http\Controllers\v1\User;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\v1\User\FollowRequest;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FollowController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(FollowRequest $request, User $user): JsonResponse
    {
        // First guard
        $isFollowing = $user->followers()->wherePivot('follower_id', $request->user()->id)->exists();

        if (! $isFollowing) {
            try {
                DB::transaction(function () use ($request, $user) {
                    // If $request->user() already followed $user, this line below will throw duplicate key violation
                    $user->followers()->attach($request->user()->id);

                    $notification = $user->notifications()
                                    ->where('type', NotificationType::AccountFollower)
                                    ->latest()
                                    ->lockForUpdate()
                                    ->first();

                    if ($notification && Carbon::parse($notification->created_at)->diffInMinutes(now(), true) < config('custom.notification.group_gap_interval')) {
                        $notification->increment('groups_count');
                    } else {
                        $notification = $user->notifications()->create([
                                            'uuid' => Str::uuid(),
                                            'type' => NotificationType::AccountFollower
                                        ]);
                    }

                    $notification->users()->attach($request->user()->id);
                });
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

        return response()->json(null, 204);
    }
}
