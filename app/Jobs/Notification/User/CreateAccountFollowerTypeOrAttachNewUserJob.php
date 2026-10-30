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
            (new WithoutOverlapping("lock:user:{$this->user->id}:notification:account_follower"))
                ->releaseAfter(60)
                ->expireAfter(240)
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheDuration = config('custom.cache.notification.ttl_duration');
        $cacheKey = str_replace('*', $this->user->id, config('custom.cache.notification.last_group.pattern.account_follower'));

        $notification = Cache::remember($cacheKey, $cacheDuration, function (): ?Notification {
                            // This query already use composite index
                            // See: database/migrations/2026_10_18_153517_create_notifications_table.php
                            return Notification::query()
                                    ->where('user_id', $this->user->id)
                                    ->where('type', NotificationType::AccountFollower->value)
                                    ->latest('created_at')
                                    ->first();
                        });

        if ($notification && Carbon::parse($notification->created_at)->diffInMinutes(now(), true) < config('custom.notification.group_gap_interval')) {
            UpdateGroupsCountCacheJob::dispatch($notification->uuid, 1)->onQueue('high');

            $notification->users()->attach($this->follower->id);
        } else {
            // Wrap in transaction
            $notification = DB::transaction(function (): Notification {
                                $notification = Notification::create([
                                    'uuid' => Str::uuid()->toString(),
                                    'type' => NotificationType::AccountFollower->value,
                                    'groups_count' => 1,
                                    'user_id' => $this->user->id
                                ]);

                                $notification->users()->attach($this->follower->id);

                                return $notification;
                            }, attempts: config('custom.database.transaction.attempts'));

            /**
             * Store the notification to cache
             */
            Cache::put($cacheKey, $notification, $cacheDuration);
        }

        UpdateNotificationsCountCacheJob::dispatch($this->user->id, 1)->onQueue('high');
    }
}
