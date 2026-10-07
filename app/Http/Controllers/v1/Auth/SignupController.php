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
            DB::transaction(function () use ($request) {
                User::create($request->validated());
            });

            return response()->json(null, 204);
        } catch (Exception $e) {
            report($e);

            throw $e;
        }
    }
}
