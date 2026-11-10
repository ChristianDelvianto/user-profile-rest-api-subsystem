<?php

namespace App\Jobs\Notification\Post;

use App\Enums\NotificationType;
use App\Jobs\Notification\UpdateGroupsCountCacheJob;
use App\Jobs\UserStat\UpdateNotificationsCountCacheJob;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePostLikeTypeOrAttachNewUserJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public Post $post
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
            (new WithoutOverlapping("lock:post:{$this->post->id}:notification:post_like"))
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

        $cacheKey = str_replace('*', $this->post->id, config('custom.cache.notification.last_group.pattern.post_like'));

        $notification = Cache::remember($cacheKey, $cacheDuration, function (): ?Notification {
                            // This query already use composite index
                            // See: database/migrations/2026_11_09_132159_add_post_id_to_notifications_table.php
                            return Notification::query()
                                    ->whereNotNull('post_id')
                                    ->where('post_id', $this->post->id)
                                    ->where('type', NotificationType::PostLike->value)
                                    ->latest('created_at')
                                    ->first();
                        });

        if ($notification && Carbon::parse($notification->created_at)->diffInMinutes(now(), true) < config('custom.notification.group_gap_interval')) {
            $notification->users()->attach($this->user->id);

            UpdateGroupsCountCacheJob::dispatch($notification->uuid, 1)->onQueue('high');
        } else {
            // Wrap in transaction
            $notification = DB::transaction(function (): Notification {
                                $notification = Notification::create([
                                                    'uuid' => Str::uuid()->toString(),
                                                    'type' => NotificationType::PostLike->value,
                                                    'groups_count' => 1,
                                                    'post_id' => $this->post->id,
                                                    'user_id' => $this->post->user_id
                                                ]);

                                $notification->users()->attach($this->user->id);

                                return $notification;
                            });

            /**
             * Store the notification to cache
             */
            Cache::put($cacheKey, $notification, $cacheDuration);
        }

        UpdateNotificationsCountCacheJob::dispatch($this->post->user_id, 1)->onQueue('high');
    }
}
