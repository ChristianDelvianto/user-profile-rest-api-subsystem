<?php

namespace App\Jobs\UserStat;

use App\Caches\UserStatCache;
use App\Models\UserStat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

class UpdateFollowingCountCacheJob implements ShouldQueue
{
    use Queueable;

    private int $newCount = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
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
            (new WithoutOverlapping("lock:user_stat:{$this->userId}:following_count"))
                ->releaseAfter(60)
                ->expireAfter(240)
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(UserStatCache $userStatCache): void
    {
        $cacheKey = $userStatCache->getKey('following_count', $this->userId);
        
        $cachedCount = Cache::get($cacheKey, null);

        if (is_null($cachedCount)) { // No cached value, get from database
            // Use DB as source of truth
            $userStats = UserStat::where('user_id', $this->userId)->firstOrFail();

            $cachedCount = $userStats->following_count;
        }

        $this->newCount = (int) $cachedCount + $this->count;

        // Fallback; we must not allow the count value to be less than 0
        if ($this->newCount < 0) {
            $this->newCount = 0;
        }

        Cache::put($cacheKey, $this->count, config('custom.cache.user_stat.ttl_duration'));
    }
}
