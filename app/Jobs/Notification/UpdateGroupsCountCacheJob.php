<?php

namespace App\Jobs\Notification;

use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

class UpdateGroupsCountCacheJob implements ShouldQueue
{
    use Queueable;

    private int $newCount = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $uuid,
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
            (new WithoutOverlapping("lock:notification:{$this->uuid}:groups_count"))
                ->releaseAfter(60)
                ->expireAfter(240)
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = str_replace('*', $this->uuid, config('custom.cache.notification.pattern.groups_count'));

        $cachedCount = Cache::get($cacheKey, null);

        if (is_null($cachedCount)) { // No cached value, get from database
            // Use DB as source of truth
            $notification = Notification::where('uuid', $this->uuid)->firstOrFail();

            $cachedCount = $notification->groups_count;
        }

        $this->newCount = (int) $cachedCount + $this->count;

        // Fallback; we must not allow the count value to be less than 0
        if ($this->newCount < 0) {
            $this->newCount = 0;
        }

        Cache::put($cacheKey, $this->newCount, config('custom.cache.notification.ttl_duration'));
    }
}
