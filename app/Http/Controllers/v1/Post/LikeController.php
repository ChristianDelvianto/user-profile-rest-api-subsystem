<?php

namespace App\Http\Controllers\v1\Post;

use App\Http\Controllers\Controller;
use App\Jobs\Notification\Post\CreatePostLikeTypeOrAttachNewUserJob;
use App\Jobs\Post\UpdateLikesCountCacheJob;
use App\Models\Post;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Post $post): JsonResponse
    {
        // @todo: explain why we return success response when post deleted
        if ($post->trashed()) {
            return response()->json(data: null, status: 204);
        }

        try {
            // If $request->user() already like the $post, this line below will throw unique violation
            $post->likes()->attachOrFail(ids: $request->user()->id);

            UpdateLikesCountCacheJob::dispatch(post: $post, count: 1)->onQueue(queue: 'high');

            // Only dispatch when $request->user() does not own the $post
            CreatePostLikeTypeOrAttachNewUserJob::dispatchIf(
                boolean: $request->user()->id !== $post->user_id,
                user: $request->user(),
                post: $post
            )
            ->onQueue(queue: 'high');

            return response()->json(data: null, status: 204);
        } catch (QueryException $e) {
            report(exception: $e);

            // $request->user() already liked the $post
            if ($e->getCode() === '23505' && str_contains(haystack: $e->errorInfo[2] ?? '', needle: 'post_like')) {
                return response()->json(data: null, status: 204);
            }

            throw $e;
        } catch (Exception $e) {
            report(exception: $e);

            throw $e;
        }
    }
}
