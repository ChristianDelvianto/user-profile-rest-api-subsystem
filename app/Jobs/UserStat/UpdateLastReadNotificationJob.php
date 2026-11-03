<?php

namespace App\Jobs\UserStat;

use App\Models\User;
use App\Models\UserStat;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;

class UpdateLastReadNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public Carbon $timestamp
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
            (new WithoutOverlapping("lock:user_stat:{$this->user->id}:last_read_notification"))
                ->releaseAfter(60)
                ->expireAfter(240)
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function (): void {
            $userStats = UserStat::where('user_id', $this->user->id)->lockForUpdate()->firstOrFail();

            if ($this->timestamp->gt($userStats->last_read_notification_at)) {
                $userStats->update(['last_read_notification_at' => $this->timestamp]);
            }
        }, attempts: config('custom.database.transaction.attempts'));
    }
}
