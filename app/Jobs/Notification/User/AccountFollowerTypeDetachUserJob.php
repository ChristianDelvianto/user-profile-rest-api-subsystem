<?php

namespace App\Jobs\Notification\User;

use App\Enums\NotificationType;
use App\Jobs\Notification\UpdateGroupsCountCacheJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AccountFollowerTypeDetachUserJob implements ShouldQueue
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
     * Execute the job.
     */
    public function handle(): void
    {
        // This query already use composite index
        // See: database/migrations/2026_10_18_153517_create_notifications_table.php
        $notification = Notification::query()
                        ->select('notifications.id', 'notifications.uuid')
                        ->join('notification_user', 'notifications.id', '=', 'notification_user.notification_id')
                        ->where('notifications.user_id', $this->user->id)
                        ->where('notifications.type', NotificationType::AccountFollower->value)
                        ->where('notification_user.user_id', $this->follower->id)
                        ->first();

        // If notification is NULL, it's already deleted by cron / scheduler
        if ($notification && 1 === $notification->users()->detach($this->follower->id)) {
            UpdateGroupsCountCacheJob::dispatch($notification->uuid, -1)->onQueue('high');
        }
    }
}
