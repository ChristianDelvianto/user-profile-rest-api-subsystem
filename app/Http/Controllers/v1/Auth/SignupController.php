<?php

namespace App\Http\Controllers\v1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\v1\Auth\SignupRequest;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SignupController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(SignupRequest $request): JsonResponse
    {
        try {
            $user = DB::transaction(function () use ($request): User {
                        $user = User::create($request->validated());

                        $user->stats()->create();

                        return $user;
                    }, attempts: config('custom.database.transaction.attempts'));

            /**
             * Do other stuff after $user created
             * 
             * Example:
             * 1. Send email for email verification
             * 2. Create $user's cache
             * 3. Create internal analytic report
             * 4. Etc
             */

            return response()->json(null, 204);
        } catch (Exception $e) {
            report($e);

            throw $e;
        }
    }
}
