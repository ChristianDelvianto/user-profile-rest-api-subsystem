<?php

namespace Tests\Feature\v1\Notification\Main;

use App\Jobs\UserStat\UpdateLastReadNotificationJob;
use App\Models\User;
use App\Models\UserStat;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Override;
use Tests\TestCase;

class ReadTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected string $userNotificationsCountCacheKey;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // This line is mandatory, otherwise the tests will throw 419 error
        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => config('app.url')
        ]);

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

        $this->user = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->userNotificationsCountCacheKey = str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.notifications_count'));
    }

    public function test_read_notification_route_exist(): void
    {
        $this->patchJson(route('api.v1.notifications.read'))
            ->assertUnauthorized();
    }

    public function test_read_notification_route_requires_authentication(): void
    {
        $this->patchJson(route('api.v1.notifications.read'))
            ->assertUnauthorized();
    }

    public function test_read_notification_will_dispatch_queues(): void
    {
        $this->actingAs($this->user, 'web')
            ->patchJson(route('api.v1.notifications.read'))
            ->assertNoContent();

        Queue::assertPushed(UpdateLastReadNotificationJob::class, function (UpdateLastReadNotificationJob $job) {
            return $job->user->id === $this->user->id;
        });
    }

    public function test_read_notification_will_set_user_notifications_count_to_0(): void
    {
        Cache::put($this->userNotificationsCountCacheKey, 10, now()->addSeconds(5));

        $this->actingAs($this->user, 'web')
            ->patchJson(route('api.v1.notifications.read'))
            ->assertNoContent();

        $this->assertTrue(Cache::has($this->userNotificationsCountCacheKey));

        $this->assertSame(0, (int) Cache::get($this->userNotificationsCountCacheKey));
    }

    public function test_read_notification_will_not_update_user_stat_last_read_notification_at_if_the_request_timestamp_equal_or_older(): void
    {
        $userStats = UserStat::firstWhere('user_id', $this->user->id);

        $currentTimestamp = $userStats->last_read_notification_at;

        // Travel one second back from $currentTimestamp
        $this->travelTo(Carbon::parse($currentTimestamp)->subSecond());

        $this->actingAs($this->user, 'web')
            ->patchJson(route('api.v1.notifications.read'))
            ->assertNoContent();

        $job = Queue::pushed(UpdateLastReadNotificationJob::class)->last();

        $this->assertNotNull($job);
        $this->assertTrue($job->timestamp->lt($currentTimestamp));

        $job->handle();

        $userStats->refresh();

        $this->assertTrue(Carbon::parse($currentTimestamp)->equalTo($userStats->last_read_notification_at));
    }

    public function test_read_notification_will_update_user_stat_last_read_notification_at(): void
    {
        $userStats = UserStat::firstWhere('user_id', $this->user->id);

        $currentTimestamp = $userStats->last_read_notification_at;

        // Travel one second forward after $currentTimestamp
        $this->travelTo(Carbon::parse($currentTimestamp)->addSecond());

        $this->actingAs($this->user, 'web')
            ->patchJson(route('api.v1.notifications.read'))
            ->assertNoContent();

        $job = Queue::pushed(UpdateLastReadNotificationJob::class)->last();

        $this->assertNotNull($job);
        $this->assertTrue($job->timestamp->gt($currentTimestamp));

        $job->handle();

        $userStats->refresh();

        $newTimestamp = $userStats->last_read_notification_at;

        $this->assertTrue(Carbon::parse($newTimestamp)->gt($currentTimestamp));
    }
}
