<?php

namespace App\Jobs\PostStat;

use App\Models\Post;
use App\Models\PostStat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

class UpdateLikesCountCacheJob implements ShouldQueue
{
    use Queueable;

    private int $newCount = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Post $post,
        public int $count
    ) {
        //
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("lock:post_stat:{$this->post->id}:likes_count"))
                ->releaseAfter(60)
                ->expireAfter(240)
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = str_replace('*', $this->post->id, config('custom.cache.post_stat.pattern.likes_count'));
        
        $cachedCount = Cache::get($cacheKey, null);

        if (is_null($cachedCount)) { // No cached value, get from database
            // Use DB as source of truth
            $postStats = PostStat::where('post_id', $this->post->id)->firstOrFail();

            $cachedCount = $postStats->likes_count;
        }

        $this->newCount = (int) $cachedCount + $this->count;

        // Fallback; we must not allow the count value to be less than 0
        if ($this->newCount < 0) {
            $this->newCount = 0;
        }

        Cache::put($cacheKey, $this->newCount, config('custom.cache.post_stat.ttl_duration'));
    }
}
