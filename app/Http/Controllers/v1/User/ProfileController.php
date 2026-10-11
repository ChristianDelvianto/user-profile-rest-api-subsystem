<?php

namespace App\Http\Controllers\v1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\v1\User\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    private bool $isFollowed = false;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, User $user): JsonResponse
    {
        if ($request->user() && $request->user()->id !== $user->id) {
            $this->isFollowed = $user->followers()->wherePivot('follower_id', $request->user()->id)->exists();
        }

        return response()->json([
            'is_followed' => $this->isFollowed,
            'profile' => ProfileResource::make($user)
        ]);
    }
}
