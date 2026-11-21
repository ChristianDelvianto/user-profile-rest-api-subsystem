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

    protected int $loopCount = 5;
    protected User $otherUser;
    protected User $user;
    protected string $otherUserFollowingCacheKey;
    protected string $userFollowersCountCacheKey;
    protected string $userFollowingCountCacheKey;
    protected string $userLatestAccountFollowerCacheKey;
    protected string $userNotificationsCountCacheKey;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();

        $this->withHeaders(['Accept' => 'application/json', 'Origin' => config('app.url')]);

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

        $this->otherUser = User::factory()->has(UserStat::factory(), 'stats')->create();
        $this->user = User::factory()->has(UserStat::factory(), 'stats')->create();

        $this->otherUserFollowingCacheKey = str_replace('*', $this->otherUser->id, config('custom.cache.user_stat.pattern.following_count'));
        $this->userFollowersCountCacheKey = str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count'));
        $this->userFollowingCountCacheKey = str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.following_count'));
        $this->userLatestAccountFollowerCacheKey = str_replace('*', $this->user->id, config('custom.cache.notification.last_group.pattern.account_follower'));
        $this->userNotificationsCountCacheKey = str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.notifications_count'));
    }

    public function test_can_follow_successfully(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class, fn (CreateAccountFollowerTypeOrAttachNewUserJob $job) => $job->handle());

        $this->assertDatabaseCount('user_follow', 1)
            ->assertDatabaseCount('notification_user', 1)
            ->assertDatabaseHas('user_follow', [
                'follower_id' => $this->otherUser->id,
                'user_id' => $this->user->id
            ])
            ->assertDatabaseHas(Notification::class, [
                'type' => NotificationType::AccountFollower->value,
                'groups_count' => 1,
                'user_id' => $this->user->id
            ]);
    }

    public function test_follow_idempotency(): void
    {
        for ($index = 0; $index < $this->loopCount; $index++) {
            $this->actingAs($this->otherUser, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();
        }

        $this->assertDatabaseCount('user_follow', 1);
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

    public function test_follow_throws_403_error_if_trying_to_follow_one_self(): void
    {
        $this->actingAs($this->user, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertForbidden();
    }

    public function test_follow_UpdateFollowersCountCacheJob_queue_does_not_run_when_queue_middleware_lock_is_held(): void
    {
        $cacheValue = 10;

        // Put initial cache value ($this->user has $cacheValue 'followers_count' cache)
        Cache::put($this->userFollowersCountCacheKey, $cacheValue, now()->addSeconds(5));

        $job = new UpdateFollowersCountCacheJob($this->user, 1);

        // Get lock key used by the job's middleware.
        $middleware = $job->middleware()[0];

        // Lock the job's middleware lock (WithoutOverlapping) for 10 seconds
        $lock = Cache::lock($middleware->getLockKey($job), 10);

        $this->assertTrue($lock->get());

        try {
            // Run through Laravel's queue middleware pipeline.
            Bus::dispatchSync($job);

            // The job should not increment the count while locked.
            $this->assertSame($cacheValue, (int) Cache::get($this->userFollowersCountCacheKey, 0));
        } finally {
            $lock->release();

            // Remove the cache
            Cache::forget($this->userFollowersCountCacheKey);
        }
    }

    public function test_follow_UpdateFollowingCountCacheJob_queue_does_not_run_when_queue_middleware_lock_is_held(): void
    {
        $cacheValue = 10;

        // Put initial cache value ($this->user has $cacheValue 'following_count' cache)
        Cache::put($this->userFollowingCountCacheKey, $cacheValue, now()->addSeconds(5));

        $job = new UpdateFollowingCountCacheJob($this->user, 1);

        // Get lock key used by the job's middleware.
        $middleware = $job->middleware()[0];

        // Lock the job's middleware lock (WithoutOverlapping) for 10 seconds
        $lock = Cache::lock($middleware->getLockKey($job), 10);

        $this->assertTrue($lock->get());

        try {
            // Run through Laravel's queue middleware pipeline.
            Bus::dispatchSync($job);

            // The job should not increment the count while locked.
            $this->assertSame($cacheValue, (int) Cache::get($this->userFollowingCountCacheKey, 0));
        } finally {
            $lock->release();

            // Remove the cache
            Cache::forget($this->userFollowingCountCacheKey);
        }
    }

    public function test_follow_will_create_new_notification_after_pass_group_gap_internal(): void
    {
        // $this->otherUser will be the first user to follow $this->user
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class, fn (CreateAccountFollowerTypeOrAttachNewUserJob $job) => $job->handle());

        // First user of the group
        Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);

        // Verify the tables
        $this->assertDatabaseCount('user_follow', 1)
            ->assertDatabaseCount('notification_user', 1)
            ->assertDatabaseCount(Notification::class, 1);

        // Travel X minutes
        $this->travel(config('custom.notification.group_gap_interval'))->minutes();

        // Another user follow $this->user
        $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

        $this->actingAs($newUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

        $this->assertDatabaseCount('user_follow', 2) // First follower + $newUser
            ->assertDatabaseCount('notification_user', 2)
            ->assertDatabaseCount(Notification::class, 2); // 2 Because the first notification has X minutes interval with the second
    }

    public function test_follow_will_dispatch_queues(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::assertPushed(UpdateFollowersCountCacheJob::class, function (UpdateFollowersCountCacheJob $job) {
            return $job->user->id === $this->user->id
                && $job->count === 1;
        });

        Queue::assertPushed(UpdateFollowingCountCacheJob::class, function (UpdateFollowingCountCacheJob $job) {
            return $job->user->id === $this->otherUser->id
                && $job->count === 1;
        });

        Queue::assertPushed(CreateAccountFollowerTypeOrAttachNewUserJob::class, function (CreateAccountFollowerTypeOrAttachNewUserJob $job) {
            return $job->follower->id === $this->otherUser->id
                && $job->user->id === $this->user->id;
        });

        // Process the job (To see if child job dispatched or not)
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class, fn (CreateAccountFollowerTypeOrAttachNewUserJob $job) => $job->handle());

        // The child job
        Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
    }

    public function test_follow_will_group_users_in_one_notification_if_followed_by_multiple_users_within_short_interval(): void
    {
        for ($index = 0; $index < $this->loopCount; $index++) {
            $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

            $this->actingAs($newUser, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

            if ($index === 0) { // First user of the notification, so no need to update groups_count
                Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
            } else {
                Queue::assertPushed(UpdateGroupsCountCacheJob::class);
            }
        }

        $this->assertDatabaseCount(Notification::class, 1)
            ->assertDatabaseCount('notification_user', $this->loopCount);
    }

    public function test_follow_will_not_dispatch_queues_if_user_already_followed(): void
    {
        $this->user->followers()->attach($this->otherUser->id);

        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::assertNothingPushed();
    }

    /**
     * Note: Time travel will not work if CACHE_STORE has value 'redis'.
     */
    public function test_follow_will_reinitiate_user_followers_count_cache_after_pass_cache_ttl_duration(): void
    {
        config(['cache.default' => 'array']);

        // $this->otherUser will be the first user to follow $this->user
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateFollowersCountCacheJob::class, fn (UpdateFollowersCountCacheJob $job) => $job->handle());

        // Ensures cache exists after being followed by $this->otherUser
        $this->assertTrue(Cache::has($this->userFollowersCountCacheKey));

        // Travel X seconds
        $this->travel(config('custom.cache.user_stat.ttl_duration'))->seconds();

        // Ensures cache expired
        $this->assertFalse(Cache::has($this->userFollowersCountCacheKey));

        // Another user follow $this->user
        $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

        $this->actingAs($newUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateFollowersCountCacheJob::class, fn (UpdateFollowersCountCacheJob $job) => $job->handle());
         
        // Ensures cache reinitiated
        $this->assertTrue(Cache::has($this->userFollowersCountCacheKey));
    }

    /**
     * Note: Time travel will not work if CACHE_STORE has value 'redis'.
     */
    public function test_follow_will_reinitiate_user_following_count_cache_after_pass_cache_ttl_duration(): void
    {
        config(['cache.default' => 'array']);

        // $this->otherUser will be the first user to follow $this->user
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateFollowingCountCacheJob::class, fn (UpdateFollowingCountCacheJob $job) => $job->handle());

        // Ensures cache exists after following $this->otherUser
        $this->assertTrue(Cache::has($this->otherUserFollowingCacheKey));

        // Travel X seconds
        $this->travel(config('custom.cache.user_stat.ttl_duration'))->seconds();

        // Ensures cache expired
        $this->assertFalse(Cache::has($this->otherUserFollowingCacheKey));

        // $this->otherUser follow another user
        $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $newUser->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateFollowingCountCacheJob::class, fn (UpdateFollowingCountCacheJob $job) => $job->handle());
         
        // Ensures cache reinitiated
        $this->assertTrue(Cache::has($this->otherUserFollowingCacheKey));
    }

    public function test_follow_will_store_new_notification_to_cache(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job (To see if cache created)
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class, fn (CreateAccountFollowerTypeOrAttachNewUserJob $job) => $job->handle());

        $this->assertTrue(Cache::has($this->userLatestAccountFollowerCacheKey));

        $notification = Cache::get($this->userLatestAccountFollowerCacheKey, null);

        $this->assertTrue($notification instanceof Notification);
    }

    public function test_follow_will_store_new_notification_to_cache_after_pass_group_gap_internal(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job (To see if cache created)
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class, fn (CreateAccountFollowerTypeOrAttachNewUserJob $job) => $job->handle());

        $firstNotification = Cache::get($this->userLatestAccountFollowerCacheKey, null);

        $this->assertTrue(isset($firstNotification) && $firstNotification instanceof Notification);

        // Travel X minutes
        $this->travel(config('custom.notification.group_gap_interval'))->minutes();

        // Another user follow $this->user
        $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

        $this->actingAs($newUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class, fn (CreateAccountFollowerTypeOrAttachNewUserJob $job) => $job->handle());

        $newNotification = Cache::get($this->userLatestAccountFollowerCacheKey, null);

        $this->assertTrue(isset($newNotification) && $newNotification instanceof Notification);

        // Compare
        $this->assertFalse($firstNotification->id === $newNotification->id);
    }

    public function test_follow_will_store_user_followers_count_to_cache(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job (To see if cache created)
        Queue::pushed(UpdateFollowersCountCacheJob::class, fn (UpdateFollowersCountCacheJob $job) => $job->handle());

        $this->assertSame(1, (int) Cache::get($this->userFollowersCountCacheKey, 0));
    }

    public function test_follow_will_store_user_following_count_to_cache(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job (To see if cache created)
        Queue::pushed(UpdateFollowingCountCacheJob::class, fn (UpdateFollowingCountCacheJob $job) => $job->handle());

        $this->assertSame(1, (int) Cache::get($this->otherUserFollowingCacheKey, 0));
    }

    public function test_follow_will_update_notifications_groups_count_cache(): void
    {
        for ($index = 0; $index < $this->loopCount; $index++) {
            $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

            $this->actingAs($newUser, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            // Process the job for notification
            Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

            // Index 0 is first user of notification
            if ($index === 0) {
                Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
            } else {
                Queue::assertPushed(UpdateGroupsCountCacheJob::class);

                // Process the job to increment notification's groups_count cache
                Queue::pushed(UpdateGroupsCountCacheJob::class)->last()->handle();
            }

            // Process the job for $this->user notifications_count
            Queue::pushed(UpdateNotificationsCountCacheJob::class)->last()->handle();

            // Ensures $this->user notifications_count cache progressively incremented
            $this->assertSame(($index + 1), (int) Cache::get($this->userNotificationsCountCacheKey, 0));
        }

        $notification = $this->user->notifications()->first();

        $groupsCountCacheKey = str_replace('*', $notification->uuid, config('custom.cache.notification.pattern.groups_count'));

        // Ensures $notification groups_count cache match with $this->loopCount
        $this->assertSame($this->loopCount, (int) Cache::get($groupsCountCacheKey, 0));
    }

    public function test_follow_will_update_user_followers_count_cache(): void
    {
        for ($index = 0; $index < $this->loopCount; $index++) {
            $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

            $this->actingAs($newUser, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            // Process the job
            Queue::pushed(UpdateFollowersCountCacheJob::class)->last()->handle();

            // Ensures $this->user followers_count cache progressively incremented
            $this->assertSame(($index + 1), (int) Cache::get($this->userFollowersCountCacheKey, 0));
        }

        // Ensures $this->user followers_count cache match with $this->loopCount
        $this->assertSame($this->loopCount, (int) Cache::get($this->userFollowersCountCacheKey, 0));
    }

    public function test_follow_will_update_user_following_count_cache(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job (To see if cache created)
        Queue::pushed(UpdateFollowingCountCacheJob::class)->last()->handle();

        $this->assertSame(1, (int) Cache::get($this->otherUserFollowingCacheKey, 0));

        // $this->otherUser follow another user
        $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $newUser->id]))
            ->assertNoContent();

        // Process the job (To see if cache created)
        Queue::pushed(UpdateFollowingCountCacheJob::class)->last()->handle();

        // Ensures $this->user following_count cache has value 2
        $this->assertSame(2, (int) Cache::get($this->otherUserFollowingCacheKey, 0));
    }

    public function test_follow_will_update_user_notifications_count_cache(): void
    {
        for ($index = 0; $index < $this->loopCount; $index++) {
            $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

            $this->actingAs($newUser, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            // Process the job for notification
            Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

            // Index 0 is first user of notification
            if ($index === 0) {
                Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
            } else {
                Queue::assertPushed(UpdateGroupsCountCacheJob::class);

                // Process the job to increment notification's groups_count cache
                Queue::pushed(UpdateGroupsCountCacheJob::class)->last()->handle();
            }

            // Process the job for $this->user notifications_count
            Queue::pushed(UpdateNotificationsCountCacheJob::class)->last()->handle();

            // Ensures $this->user notifications_count cache progressively incremented
            $this->assertSame(($index + 1), (int) Cache::get($this->userNotificationsCountCacheKey, 0));
        }

        // Ensures $this->user notifications_count cache match with $this->loopCount
        $this->assertSame($this->loopCount, (int) Cache::get($this->userNotificationsCountCacheKey, 0));
    }
}
