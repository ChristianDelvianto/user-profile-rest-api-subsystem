<?php

namespace App\Http\Controllers\v1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\v1\User\FollowRequest;
use App\Models\User;
use Exception;
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
        $isFollowing = $user->followers()->wherePivot('follower_id', $request->user()->id)->exists();

        if (! $isFollowing) {
            try {
                DB::transaction(function () use ($request, $user) {
                    $user->followers()->attach($request->user()->id);
                });
            } catch (Exception $e) {
                report($e);

                throw $e;
            }
        }

        return response()->json(null, 204);
    }
}
