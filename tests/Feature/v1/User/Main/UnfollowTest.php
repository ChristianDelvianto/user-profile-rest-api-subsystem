<?php

namespace Tests\Feature\v1\User\Main;

use App\Enums\NotificationType;
use App\Jobs\Notification\UpdateGroupsCountCacheJob;
use App\Jobs\Notification\User\AccountFollowerTypeDetachUserJob;
use App\Jobs\Notification\User\CreateAccountFollowerTypeOrAttachNewUserJob;
use App\Jobs\UserStat\UpdateFollowersCountCacheJob;
use App\Jobs\UserStat\UpdateFollowingCountCacheJob;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserStat;
use GuzzleHttp\Promise\Create;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Override;
use Tests\TestCase;

class UnfollowTest extends TestCase
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

    public function test_can_unfollow_successfully(): void
    {
        // First, $this->otherUser will follow $this->user
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class, fn (CreateAccountFollowerTypeOrAttachNewUserJob $job) => $job->handle());

        // $this->otherUser decided to unfollow, in real-life scenario user could be misclicked
        $this->travel(2)->seconds();

        $this->actingAs($this->otherUser, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::pushed(AccountFollowerTypeDetachUserJob::class, fn (AccountFollowerTypeDetachUserJob $job) => $job->handle());

        $this->assertDatabaseCount('user_follow', 0)
            ->assertDatabaseCount('notification_user', 0)
            ->assertDatabaseMissing('user_follow', [
                'follower_id' => $this->otherUser->id,
                'user_id' => $this->user->id
            ])
            ->assertDatabaseHas(Notification::class, [
                'type' => NotificationType::AccountFollower->value,
                'groups_count' => 1, // Ensure notification not updated
                'user_id' => $this->user->id
            ]);
    }

    public function test_unfollow_idempotency(): void
    {
        $this->user->followers()->attach($this->otherUser->id);

        for ($index = 0; $index < $this->loopCount; $index++) {
            $this->actingAs($this->otherUser, 'web')
                ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
                ->assertNoContent();
        }

        $this->assertDatabaseCount('user_follow', 0)
            ->assertDatabaseMissing('user_follow', [
                'follower_id' => $this->otherUser->id,
                'user_id' => $this->user->id
            ]);
    }

    public function test_unfollow_route_exists(): void
    {
        $this->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertUnauthorized();
    }

    public function test_unfollow_route_requires_authentication(): void
    {
        $this->postJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertUnauthorized();
    }

    public function test_unfollow_throws_403_error_if_trying_to_unfollow_one_self(): void
    {
        $this->actingAs($this->user, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertForbidden();
    }

    public function test_unfollow_UpdateFollowersCountCacheJob_queue_does_not_run_when_queue_middleware_lock_is_held(): void
    {
        $cacheValue = 10;

        // Put initial cache value ($this->user has 10 'followers_count' cache)
        Cache::put($this->userFollowersCountCacheKey, $cacheValue, now()->addSeconds(5));

        $job = new UpdateFollowersCountCacheJob($this->user, -1);

        // Get lock key used by the job's middleware.
        $middleware = $job->middleware()[0];

        // Lock the job's middleware lock (WithoutOverlapping) for 10 seconds
        $lock = Cache::lock($middleware->getLockKey($job), 10);

        $this->assertTrue($lock->get());

        try {
            // Run through Laravel's queue middleware pipeline.
            Bus::dispatchSync($job);

            // The job should not increment the count while locked.
            $this->assertSame($cacheValue, (int) Cache::get($this->userFollowersCountCacheKey));
        } finally {
            $lock->release();

            // Remove the cache
            Cache::forget($this->userFollowersCountCacheKey);
        }
    }

    public function test_unfollow_UpdateFollowingCountCacheJob_queue_does_not_run_when_queue_middleware_lock_is_held(): void
    {
        $cacheValue = 10;

        // Put initial cache value ($this->user has 10 'following_count' cache)
        Cache::put($this->userFollowingCountCacheKey, $cacheValue, now()->addSeconds(5));

        $job = new UpdateFollowingCountCacheJob($this->user, -1);

        // Get lock key used by the job's middleware.
        $middleware = $job->middleware()[0];

        // Lock the job's middleware lock (WithoutOverlapping) for 10 seconds
        $lock = Cache::lock($middleware->getLockKey($job), 10);

        $this->assertTrue($lock->get());

        try {
            // Run through Laravel's queue middleware pipeline.
            Bus::dispatchSync($job);

            // The job should not increment the count while locked.
            $this->assertSame($cacheValue, (int) Cache::get($this->userFollowingCountCacheKey));
        } finally {
            $lock->release();

            // Remove the cache
            Cache::forget($this->userFollowingCountCacheKey);
        }
    }

    public function test_unfollow_will_dispatch_queues(): void
    {
        $this->user->followers()->attach($this->otherUser->id);

        $notification = $this->user->notifications()
                        ->create([
                            'uuid' => Str::uuid()->toString(),
                            'type' => NotificationType::AccountFollower->value,
                            'groups_count' => 1
                        ]);

        $notification->users()->attach($this->otherUser->id);

        $this->actingAs($this->otherUser, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::assertPushed(UpdateFollowersCountCacheJob::class, function ($job) {
            return $job->user->id === $this->user->id
                && $job->count === -1;
        });

        Queue::assertPushed(UpdateFollowingCountCacheJob::class, function ($job) {
            return $job->user->id === $this->otherUser->id
                && $job->count === -1;
        });

        Queue::assertPushed(AccountFollowerTypeDetachUserJob::class, function ($job) {
            return $job->follower->id === $this->otherUser->id
                && $job->user->id === $this->user->id;
        });

        Queue::assertPushed(AccountFollowerTypeDetachUserJob::class, function ($job) {
            return $job->follower->id === $this->otherUser->id
                && $job->user->id === $this->user->id;
        });

        // Job to detach user from $notification group
        Queue::pushed(AccountFollowerTypeDetachUserJob::class)->last()->handle();

        // Job to update $notification groups_count cache
        Queue::assertPushed(UpdateGroupsCountCacheJob::class, function ($job) use ($notification) {
            return $job->notification->uuid === $notification->uuid
                && $job->count === -1;
        });
    }

    public function test_unfollow_will_fallback_notification_groups_count_cache_value_to_0_if_counter_drop_below_0(): void
    {
        $users = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->count($this->loopCount)
                ->create();

        foreach ($users as $index => $user) {
            $this->actingAs($user, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

            if ($index > 0) {
                Queue::pushed(UpdateGroupsCountCacheJob::class)->last()->handle();
            }
        }

        $notification = $this->user->notifications()->first();

        $groupsCountCacheKey = str_replace('*', $notification->uuid, config('custom.cache.notification.pattern.groups_count'));

        // We will update $notification groups_count cache to 0
        Cache::put($groupsCountCacheKey, 0, now()->addSeconds(5));

        // Some users from $users decided to unfollow
        $removeCount = rand(1, ($this->loopCount - 1));

        foreach ($users->random($removeCount)->all() as $user) {
            $this->actingAs($user, 'web')
                ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
                ->assertNoContent();

            Queue::pushed(AccountFollowerTypeDetachUserJob::class)->last()->handle();

            Queue::pushed(UpdateGroupsCountCacheJob::class)->last()->handle();
        }

        // Ensures $notification has groups_count cache
        $this->assertTrue(Cache::has($groupsCountCacheKey));

        // Ensures $notification groups_count cache has 0 value (not -1)
        $this->assertSame(0, Cache::get($groupsCountCacheKey, null));
    }

    public function test_unfollow_will_fallback_user_followers_count_cache_value_to_0_if_counter_drop_below_0(): void
    {
        // First we set $this->otherUser as $this->user follower, (No cache and $this->user followers_count in user_stats table has value 0)
        $this->user->followers()->attach($this->otherUser->id);

        $this->actingAs($this->otherUser, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::pushed(UpdateFollowersCountCacheJob::class, fn (UpdateFollowersCountCacheJob $job) => $job->handle());

        $this->assertSame(0, Cache::get($this->userFollowersCountCacheKey, null));
    }

    public function test_unfollow_will_fallback_user_following_count_cache_value_to_0_if_counter_drop_below_0(): void
    {
        // First we set $this->otherUser as $this->user follower, (No cache and $this->otherUser following_count in user_stats table has value 0)
        $this->user->followers()->attach($this->otherUser->id);

        $this->actingAs($this->otherUser, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::pushed(UpdateFollowingCountCacheJob::class, fn (UpdateFollowingCountCacheJob $job) => $job->handle());

        $this->assertSame(0, Cache::get($this->otherUserFollowingCacheKey, null));
    }

    public function test_unfollow_will_not_dispatch_queues_if_user_not_followed(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::assertNothingPushed();
    }

    /**
     * Note: Time travel will not work if CACHE_STORE has value 'redis'.
     */
    public function test_unfollow_will_reinitiate_notification_groups_count_cache_after_pass_cache_ttl_duration(): void
    {
        config(['cache.default' => 'array']);

        // We will create a few followers for $this->user
        for ($index = 0; $index < $this->loopCount; $index++) {
            $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

            $this->actingAs($newUser, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

            if ($index > 0) {
                Queue::pushed(UpdateGroupsCountCacheJob::class)->last()->handle();
            }

            // The last $newUser will unfollow $this->user
            if (($index + 1) === $this->loopCount) {
                // Get $this->user notification
                $notification = $this->user->notifications()->first();

                $groupsCountCacheKey = str_replace('*', $notification->uuid, config('custom.cache.notification.pattern.groups_count'));

                // Ensures $notification has groups_count cache
                $this->assertTrue(Cache::has($groupsCountCacheKey));

                // Travel X seconds
                $this->travel(config('custom.cache.user_stat.ttl_duration'))->seconds();

                // Ensures $notifications groups_count cache expired
                $this->assertFalse(Cache::has($groupsCountCacheKey));

                $this->actingAs($newUser, 'web')
                    ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
                    ->assertNoContent();

                Queue::pushed(AccountFollowerTypeDetachUserJob::class, fn (AccountFollowerTypeDetachUserJob $job) => $job->handle());

                Queue::pushed(UpdateGroupsCountCacheJob::class, fn (UpdateGroupsCountCacheJob $job) => $job->handle());

                // Ensures $notification groups_count cache reinitiated
                $this->assertTrue(Cache::has($groupsCountCacheKey));
            }
        }
    }

    /**
     * Note: Time travel will not work if CACHE_STORE has value 'redis'.
     */
    public function test_unfollow_will_reinitiate_user_followers_count_cache_after_pass_cache_ttl_duration(): void
    {
        config(['cache.default' => 'array']);

        // First, $this->otherUser will follow $this->user
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job ($this->user now should have followers_count cache)
        Queue::pushed(UpdateFollowersCountCacheJob::class, fn (UpdateFollowersCountCacheJob $job) => $job->handle());

        $this->assertTrue(Cache::has($this->userFollowersCountCacheKey));

        // Travel X seconds
        $this->travel(config('custom.cache.user_stat.ttl_duration'))->seconds();

        // Ensures cache expired
        $this->assertFalse(Cache::has($this->userFollowersCountCacheKey));

        // $this->otherUser decided to unfollow
        $this->actingAs($this->otherUser, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateFollowersCountCacheJob::class, fn (UpdateFollowersCountCacheJob $job) => $job->handle());

        // Ensures cache reinitiated
        $this->assertTrue(Cache::has($this->userFollowersCountCacheKey));
    }

    /**
     * Note: Time travel will not work if CACHE_STORE has value 'redis'.
     */
    public function test_unfollow_will_reinitiate_user_following_count_cache_after_pass_cache_ttl_duration(): void
    {
        config(['cache.default' => 'array']);

        // First, $this->otherUser will follow $this->user
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job ($this->otherUser now should have following_count cache)
        Queue::pushed(UpdateFollowingCountCacheJob::class, fn (UpdateFollowingCountCacheJob $job) => $job->handle());

        $this->assertTrue(Cache::has($this->otherUserFollowingCacheKey));

        // Travel X seconds
        $this->travel(config('custom.cache.user_stat.ttl_duration'))->seconds();

        // Ensures cache expired
        $this->assertFalse(Cache::has($this->otherUserFollowingCacheKey));

        // $this->otherUser decided to unfollow
        $this->actingAs($this->otherUser, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateFollowingCountCacheJob::class, fn (UpdateFollowingCountCacheJob $job) => $job->handle());

        // Ensures cache reinitiated
        $this->assertTrue(Cache::has($this->otherUserFollowingCacheKey));
    }

    public function test_unfollow_will_remove_user_from_notification_group(): void
    {
        // $this->otherUser will be the first user to follow $this->user
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class, fn (CreateAccountFollowerTypeOrAttachNewUserJob $job) => $job->handle());

        $this->assertDatabaseHas('notification_user', [
            // No need to add 'notification_id' here
            'user_id' => $this->otherUser->id
        ]);

        $this->actingAs($this->otherUser, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::pushed(AccountFollowerTypeDetachUserJob::class, fn (AccountFollowerTypeDetachUserJob $job) => $job->handle());

        $this->assertDatabaseMissing('notification_user', [
            // No need to add 'notification_id' here
            'user_id' => $this->otherUser->id
        ]);
    }

    public function test_unfollow_will_update_notification_groups_count_cache(): void
    {
        $users = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->count($this->loopCount)
                ->create();

        foreach ($users as $index => $user) {
            $this->actingAs($user, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            // Process the job for notification
            Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

            // Index 0 is first user of notification
            if ($index > 0) {
                // Process the job to increment notification's groups_count cache
                Queue::pushed(UpdateGroupsCountCacheJob::class)->last()->handle();
            }
        }

        $notification = $this->user->notifications()->first();

        $groupsCountCacheKey = str_replace('*', $notification->uuid, config('custom.cache.notification.pattern.groups_count'));

        // Ensures $notification has groups_count cache
        $this->assertTrue(Cache::has($groupsCountCacheKey));

        $this->assertSame($this->loopCount, Cache::get($groupsCountCacheKey, null));

        // Some users from $users decided to unfollow
        $removeCount = rand(1, ($this->loopCount - 1));

        foreach ($users->random($removeCount)->all() as $user) {
            $this->actingAs($user, 'web')
                ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
                ->assertNoContent();

            Queue::pushed(AccountFollowerTypeDetachUserJob::class)->last()->handle();

            Queue::pushed(UpdateGroupsCountCacheJob::class)->last()->handle();
        }

        $this->assertSame(($this->loopCount - $removeCount), Cache::get($groupsCountCacheKey, null));
    }

    public function test_unfollow_will_update_user_followers_count_cache(): void
    {
        $users = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->count($this->loopCount)
                ->create();

        foreach ($users as $user) {
            $this->actingAs($user, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            // Process the job
            Queue::pushed(UpdateFollowersCountCacheJob::class)->last()->handle();
        }

        // Ensures $this->user has followers_count cache
        $this->assertTrue(Cache::has($this->userFollowersCountCacheKey));

        // Some users from $users decided to unfollow
        $removeCount = rand(1, ($this->loopCount - 1));

        foreach ($users->random($removeCount)->all() as $user) {
            $this->actingAs($user, 'web')
                ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
                ->assertNoContent();

            // Process the job
            Queue::pushed(UpdateFollowersCountCacheJob::class)->last()->handle();
        }

        $this->assertSame(($this->loopCount - $removeCount), (int) Cache::get($this->userFollowersCountCacheKey, 0));
    }

    public function test_unfollow_will_update_user_following_count_cache(): void
    {
        $users = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->count($this->loopCount)
                ->create();

        // $this->otherUser will follow $users
        foreach ($users as $user) {
            $this->actingAs($this->otherUser, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $user->id]))
                ->assertNoContent();

            // Process the job
            Queue::pushed(UpdateFollowingCountCacheJob::class)->last()->handle();
        }

        $this->assertSame($this->loopCount, (int) Cache::get($this->otherUserFollowingCacheKey, 0));

        // Now $this->otherUser will unfollow some users from $users
        $removeCount = rand(1, ($this->loopCount - 1));

        foreach ($users->random($removeCount)->all() as $user) {
            $this->actingAs($this->otherUser, 'web')
                ->deleteJson(route('api.v1.user.unfollow', ['user' => $user->id]))
                ->assertNoContent();

            // Process the job
            Queue::pushed(UpdateFollowingCountCacheJob::class)->last()->handle();
        }

        $this->assertSame(($this->loopCount - $removeCount), (int) Cache::get($this->otherUserFollowingCacheKey, 0));
    }
}
