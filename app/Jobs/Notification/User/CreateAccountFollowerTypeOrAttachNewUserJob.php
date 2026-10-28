<?php

namespace App\Jobs\Notification\User;

use App\Enums\NotificationType;
use App\Jobs\Notification\UpdateGroupsCountCacheJob;
use App\Jobs\UserStat\UpdateNotificationsCountCacheJob;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAccountFollowerTypeOrAttachNewUserJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $follower,
        public User $user
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
            (new WithoutOverlapping("lock:notification:user:{$this->user->id}:account_follower"))
                ->releaseAfter(60)
                ->expireAfter(240)
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = str_replace('*', $this->user->id, config('custom.cache.notification.grouped_notification.pattern.account_follower'));

        $notification = Cache::get($cacheKey, null);

        if ($notification && Carbon::parse($notification->created_at)->diffInMinutes(now(), true) < config('custom.notification.group_gap_interval')) {
            UpdateGroupsCountCacheJob::dispatch($notification->uuid, 1)->onQueue('high');

            $notification->users()->attach($this->follower->id);
        } else {
            // Wrap in transaction
            $notification = DB::transaction(function (): Notification {
                                $notification = Notification::create([
                                    'uuid' => Str::uuid(),
                                    'type' => NotificationType::AccountFollower,
                                    'groups_count' => 1,
                                    'user_id' => $this->user->id
                                ]);

                                $notification->users()->attach($this->follower->id);

                                return $notification;
                            }, attempts: config('custom.database.transaction.attempts'));

            Cache::put($cacheKey, $notification, config('custom.cache.notification.ttl_duration'));
        }

        UpdateNotificationsCountCacheJob::dispatch($this->user->id, 1)->onQueue('high');
    }
}
