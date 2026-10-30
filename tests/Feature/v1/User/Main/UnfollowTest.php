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

    protected User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
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
    }

    public function test_can_unfollow_successfully(): void
    {
        $follower = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->actingAs($follower, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::assertPushed(CreateAccountFollowerTypeOrAttachNewUserJob::class);

        Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

        // $follower decided to unfollow, in real-life scenario user could be misclicked
        $this->travel(2)->seconds();

        $this->actingAs($follower, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::assertPushed(AccountFollowerTypeDetachUserJob::class);

        // Job to detach $follower from $notification
        Queue::pushed(AccountFollowerTypeDetachUserJob::class)->last()->handle();

        $this->assertDatabaseCount('user_follow', 0)
            ->assertDatabaseCount('notification_user', 0)
            ->assertDatabaseMissing('user_follow', [
                'follower_id' => $follower->id,
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
        $follower = User::factory()->create();

        for ($index = 0; $index < 10; $index++) {
            $this->actingAs($follower, 'web')
                ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
                ->assertNoContent();
        }

        $this->assertDatabaseCount('user_follow', 0)
            ->assertDatabaseMissing('user_follow', [
                'follower_id' => $follower->id,
                'user_id' => $this->user->id
            ]);
    }

    public function test_unfollow_one_self_will_throw_403_error(): void
    {
        $this->actingAs($this->user, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertForbidden();
    }

    public function test_unfollow_queue_does_not_run_when_queue_middleware_lock_is_held(): void
    {
        $cacheKey = str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count'));

        // Put initial value 10 ($this->user has 10 'followers_count' cache)
        Cache::put($cacheKey, 10, now()->addSeconds(10));

        $job = new UpdateFollowersCountCacheJob($this->user->id, -1);

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

    public function test_unfollow_will_dispatch_queues(): void
    {
        $follower = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->user->followers()->attach($follower->id);

        $notification = $this->user->notifications()
                        ->create([
                            'uuid' => Str::uuid()->toString(),
                            'type' => NotificationType::AccountFollower,
                            'groups_count' => 1
                        ]);

        $notification->users()->attach($follower->id);

        $this->actingAs($follower, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::assertPushed(UpdateFollowersCountCacheJob::class, function ($job) {
            return $job->userId === $this->user->id
                && $job->count === -1;
        });

        Queue::assertPushed(UpdateFollowingCountCacheJob::class, function ($job) use ($follower) {
            return $job->userId === $follower->id
                && $job->count === -1;
        });

        Queue::assertPushed(AccountFollowerTypeDetachUserJob::class, function ($job) use ($follower) {
            return $job->follower->id === $follower->id
                && $job->user->id === $this->user->id;
        });

        Queue::assertPushed(AccountFollowerTypeDetachUserJob::class, function ($job) use ($follower) {
            return $job->follower->id === $follower->id
                && $job->user->id === $this->user->id;
        });

        // Job to detach $follower from $notification
        Queue::pushed(AccountFollowerTypeDetachUserJob::class)->last()->handle();

        // Job to update $notification groups_count cache
        Queue::assertPushed(UpdateGroupsCountCacheJob::class, function ($job) use ($notification) {
            return $job->uuid === $notification->uuid
                && $job->count === -1;
        });
    }

    public function test_unfollow_will_not_dispatch_queues_if_user_not_followed(): void
    {
        $follower = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        // Will return 204, idempotent
        $this->actingAs($follower, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        Queue::assertNothingPushed();
    }

    /**
     * Note: Time travel will not work if CACHE_STORE has value 'redis',
     * use other driver and clear app cache.
     */
    public function test_unfollow_will_reinitiate_followers_count_cache_after_some_time(): void
    {
        $user = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->create();

        $this->actingAs($user, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateFollowersCountCacheJob::class)->last()->handle();

        $this->travel(config('custom.cache.user_stat.ttl_duration'))->seconds();

        // Ensures cache expired
        $this->assertFalse(Cache::has(str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count'))));

        // $user decided to unfollow
        $this->actingAs($user, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateFollowersCountCacheJob::class)->last()->handle();

        // Ensures cache reinitiated
        $this->assertTrue(Cache::has(str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count'))));
    }

    public function test_unfollow_will_update_stats_count_cache(): void
    {
        $usersCount = 10;

        // Set initial followers_count cache for $this->user
        Cache::put(str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count')), $usersCount, now()->addSeconds(10));

        for ($index = $usersCount; $index > 0; $index--) {
            $follower = User::factory()
                        ->has(UserStat::factory(), 'stats')
                        ->create();

            // Attach $follower first
            $this->user->followers()->attach($follower->id);

            // Set following_count cache for $follower
            Cache::put(str_replace('*', $follower->id, config('custom.cache.user_stat.pattern.following_count')), 1, now()->addSeconds(10));

            // The $follower canceled their follow
            $this->actingAs($follower, 'web')
                ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
                ->assertNoContent();

            $this->assertDatabaseMissing('user_follow', [
                'follower_id' => $follower->id,
                'user_id' => $this->user->id
            ]);

            // Process the job
            Queue::pushed(UpdateFollowersCountCacheJob::class)->last()->handle();

            // Process the job (For $follower)
            Queue::pushed(UpdateFollowingCountCacheJob::class)->last()->handle();

            // Ensures $this->user cached followers_count progressively decremented
            $this->assertEquals(($index - 1), Cache::get(str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.followers_count'))));

            // Ensures follower has following_count cache is 0
            $this->assertEquals(0, Cache::get(str_replace('*', $follower->id, config('custom.cache.user_stat.pattern.following_count')), null));
        }
    }
}
