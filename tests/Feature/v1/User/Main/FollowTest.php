<?php

namespace Tests\Feature\v1\User\Main;

use App\Enums\NotificationType;
use App\Jobs\Notification\UpdateGroupsCountCacheJob;
use App\Jobs\Notification\User\CreateAccountFollowerTypeOrAttachNewUserJob;
use App\Jobs\UserStat\UpdateFollowersCountCacheJob;
use App\Jobs\UserStat\UpdateFollowingCountCacheJob;
use App\Jobs\UserStat\UpdateNotificationsCountCacheJob;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Override;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

        $this->user = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();
    }

    public function test_can_follow_successfully(): void
    {
        $follower = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->actingAs($follower, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

        // Ensure UpdateGroupsCountCacheJob not pushed (first user of the notification)
        Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);

        $this->assertDatabaseCount('user_follow', 1)
            ->assertDatabaseCount('notification_user', 1)
            ->assertDatabaseHas('user_follow', [
                'follower_id' => $follower->id,
                'user_id' => $this->user->id
            ])
            ->assertDatabaseHas(Notification::class, [
                'type' => NotificationType::AccountFollower,
                'user_id' => $this->user->id
            ]);
    }

    public function test_follow_after_X_minutes_will_create_new_notification(): void
    {
        $firstFollower = User::factory()
                        ->has(UserStat::factory(), 'stats')
                        ->create();

        $this->actingAs($firstFollower, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

        $this->assertDatabaseCount('user_follow', 1)
            ->assertDatabaseCount('notification_user', 1)
            ->assertDatabaseCount(Notification::class, 1);

        // Travel X minutes
        $this->travel(config('custom.notification.group_gap_interval'))->minutes();

        $newFollowersCount = 10;
        
        // Get 10 more followers
        for ($index = 0; $index < $newFollowersCount; $index++) {
            $newFollower = User::factory()
                        ->has(UserStat::factory(), 'stats')
                        ->create();

            $this->actingAs($newFollower, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            $this->assertDatabaseHas('user_follow', [
                'follower_id' => $newFollower->id,
                'user_id' => $this->user->id
            ]);

            // Process the job
            Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)
                ->last() // Must use last() here
                ->handle();

            if ($index === 0) {
                // Assert no other users belong to the notification, because its first user of the group
                Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
            } else {
                Queue::assertPushed(UpdateGroupsCountCacheJob::class);
            }
        }

        $this->assertDatabaseCount('user_follow', 11) // First follower + $newFollowersCount (10)
            ->assertDatabaseCount('notification_user', 11)
            ->assertDatabaseCount(Notification::class, 2); // 2 Because the first notification has X minutes interval with the second
    }

    public function test_follow_from_multiple_users_within_short_interval_will_group_the_users_in_one_notification(): void
    {
        $followersCount = 10;

        for ($index = 0; $index < $followersCount; $index++) {
            $follower = User::factory()
                        ->has(UserStat::factory(), 'stats')
                        ->create();

            $this->actingAs($follower, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            Queue::assertPushed(CreateAccountFollowerTypeOrAttachNewUserJob::class);

            // Process the job
            Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

            $this->assertDatabaseHas('notification_user', ['user_id' => $follower->id]);

            if ($index === 0) {
                Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
            } else {
                Queue::assertPushed(UpdateGroupsCountCacheJob::class);
            }
        }

        $this->assertDatabaseCount('user_follow', $followersCount)
            ->assertDatabaseCount('notification_user', $followersCount)
            ->assertDatabaseCount(Notification::class, 1)
            ->assertDatabaseHas(Notification::class, [
                'type' => NotificationType::AccountFollower,
                'groups_count' => 1, // Ensures groups_count not updated
                'user_id' => $this->user->id
            ]);
    }

    public function test_follow_idempotency(): void
    {
        $follower = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        for ($index = 0; $index < 10; $index++) {
            $this->actingAs($follower, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();
        }

        $this->assertDatabaseCount('user_follow', 1)
            ->assertDatabaseHas('user_follow', [
                'follower_id' => $follower->id,
                'user_id' => $this->user->id
            ]);
    }

    public function test_follow_one_self_will_throw_403_error(): void
    {
        $this->actingAs($this->user, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertForbidden();
    }

    public function test_follow_queue_does_not_run_when_queue_middleware_lock_is_held(): void
    {
        $cacheKey = str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count'));

        // Put initial value 10 ($this->user has 10 'followers_count' cache)
        Cache::put($cacheKey, 10, now()->addSeconds(10));

        $job = new UpdateFollowersCountCacheJob($this->user->id, 1);

        // Get lock key used by the job's middleware.
        $middleware = $job->middleware()[0];

        // Lock the job's middleware lock (WithoutOverlapping) for 10 seconds
        $lock = Cache::lock($middleware->getLockKey($job), 10);

        $this->assertTrue($lock->get());

        try {
            // Run through Laravel's queue middleware pipeline.
            Bus::dispatchSync($job);

            // The job should not increment the count while locked.
            $this->assertSame(10, (int) Cache::get($cacheKey));
        } finally {
            $lock->release();

            // Remove the cache
            Cache::forget($cacheKey);
        }
    }

    public function test_follow_route_exists(): void
    {
        $this->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertUnauthorized();
    }

    public function test_follow_route_requires_authentication(): void
    {
        $this->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertUnauthorized();
    }

    public function test_follow_will_dispatch_queues(): void
    {
        $follower = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->actingAs($follower, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::assertPushed(UpdateFollowersCountCacheJob::class, function ($job) {
            return $job->userId === $this->user->id
                && $job->count === 1;
        });

        Queue::assertPushed(UpdateFollowingCountCacheJob::class, function ($job) use ($follower) {
            return $job->userId === $follower->id
                && $job->count === 1;
        });

        Queue::assertPushed(CreateAccountFollowerTypeOrAttachNewUserJob::class, function ($job) use ($follower) {
            return $job->follower->id === $follower->id
                && $job->user->id === $this->user->id;
        });

        // Process the job (To see if child job dispatched or not)
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

        Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
    }

    public function test_follow_will_not_dispatch_queues_if_user_already_followed(): void
    {
        $follower = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->user->followers()->attach($follower->id);

        // Will return 204, idempotent
        $this->actingAs($follower, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::assertNothingPushed();
    }

    /**
     * Note: Time travel will not work if CACHE_STORE has value 'redis',
     * use other driver and clear app cache.
     */
    public function test_follow_will_reinitiate_followers_count_cache_after_some_time(): void
    {
        $firstUser = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->actingAs($firstUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateFollowersCountCacheJob::class)->last()->handle();

        // Ensures cache exists after being followed by $firstUser
        $this->assertTrue(Cache::has(str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count'))));

        $this->travel(config('custom.cache.user_stat.ttl_duration'))->seconds();

        // Ensures cache expired
        $this->assertFalse(Cache::has(str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count'))));

        $secondUser = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->actingAs($secondUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateFollowersCountCacheJob::class)->last()->handle();
         
        // Ensures cache reinitiated
        $this->assertTrue(Cache::has(str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count'))));
    }

    public function test_follow_will_store_related_stats_count_to_cache(): void
    {
        $follower = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->actingAs($follower, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        $this->assertDatabaseHas('user_follow', [
            'follower_id' => $follower->id,
            'user_id' => $this->user->id
        ]);

        // Process the job (To see if cache created)
        Queue::pushed(UpdateFollowersCountCacheJob::class, function ($job) {
            $job->handle();
        });

        // Process the job (To see if cache created)
        Queue::pushed(UpdateFollowingCountCacheJob::class, function ($job) {
            $job->handle();
        });

        // Ensures tables is not updated
        $this->assertDatabaseHas(UserStat::class, [
                'user_id' => $this->user->id,
                'followers_count' => 0
            ])
            ->assertDatabaseHas(UserStat::class, [
                'user_id' => $follower->id,
                'followers_count' => 0
            ]);

        $this->assertSame(1, (int) Cache::get(str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count')), 0));
        $this->assertSame(1, (int) Cache::get(str_replace('*', $follower->id, config('custom.cache.user_stat.pattern.following_count')), 0));
    }

    public function test_follow_will_update_stats_count_cache(): void
    {
        $usersCount = 10;

        for ($index = 0; $index < $usersCount; $index++) {
            $follower = User::factory()
                        ->has(UserStat::factory(), 'stats')
                        ->create();

            $this->actingAs($follower, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            // Process the job
            Queue::pushed(UpdateFollowersCountCacheJob::class)->last()->handle();

            // Process the job (For $follower)
            Queue::pushed(UpdateFollowingCountCacheJob::class)->last()->handle();

            // Process the job for notification
            Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

            // Index 0 is first user of notification
            if ($index === 0) {
                Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
            } else {
                // Process the job for notification groups_count cache
                Queue::pushed(UpdateGroupsCountCacheJob::class)->last()->handle();
            }

            // Process the job for $this->user notifications_count
            Queue::pushed(UpdateNotificationsCountCacheJob::class)->last()->handle();

            // Ensures $follower has 1 following_count cache value
            $this->assertSame(1, (int) Cache::get(str_replace('*', $follower->id, config('custom.cache.user_stat.pattern.following_count')), 0));

            // Ensures $this->user followers_count cache value progressively incremented
            $this->assertSame(($index + 1), (int) Cache::get(str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count')), 0));

            // Ensures $this->user notifications_count cache value progressively incremented
            $this->assertSame(($index + 1), (int) Cache::get(str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.notifications_count')), 0));
        }
    }
}
