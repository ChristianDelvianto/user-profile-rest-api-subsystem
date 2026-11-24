<?php

namespace Tests\Feature\v1\Post\Main;

use App\Enums\NotificationType;
use App\Jobs\Notification\Post\CreatePostLikeTypeOrAttachNewUserJob;
use App\Jobs\Notification\UpdateGroupsCountCacheJob;
use App\Jobs\PostStat\UpdateLikesCountCacheJob;
use App\Jobs\UserStat\UpdateNotificationsCountCacheJob;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostStat;
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

class LikeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected int $loopCount = 5;
    protected Post $post;
    protected User $otherUser;
    protected User $user;
    protected string $postLatestLikesNotificationCacheKey;
    protected string $postLikesCountCacheKey;
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

        // $this->post belongs to $this->user
        $this->post = Post::factory()->has(PostStat::factory(), 'stats')->create(['user_id' => $this->user->id]);

        $this->postLatestLikesNotificationCacheKey = str_replace('*', $this->post->id, config('custom.cache.notification.last_group.pattern.post_like'));
        $this->postLikesCountCacheKey = str_replace('*', $this->post->id, config('custom.cache.post_stat.pattern.likes_count'));
        $this->userNotificationsCountCacheKey = str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.notifications_count'));
    }

    public function test_can_like_successfully(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class, fn (CreatePostLikeTypeOrAttachNewUserJob $job) => $job->handle());

        $this->assertDatabaseCount('post_like', 1)
            ->assertDatabaseCount('notification_user', 1)
            ->assertDatabaseHas('post_like', [
                'post_id' => $this->post->id,
                'user_id' => $this->otherUser->id
            ])
            ->assertDatabaseHas(Notification::class, [
                'type' => NotificationType::PostLike->value,
                'groups_count' => 1,
                'post_id' => $this->post->id,
                'user_id' => $this->user->id
            ]);
    }

    public function test_like_idempotency(): void
    {
        for ($index = 0; $index < $this->loopCount; $index++) {
            $this->actingAs($this->otherUser, 'web')
                ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
                ->assertNoContent();
        }

        $this->assertDatabaseCount('post_like', 1);
    }

    public function test_like_returns_response_204_code_although_post_deleted(): void
    {
        // Soft delete the post
        $this->post->delete();

        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();
    }


    public function test_like_route_exists(): void
    {
        $this->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertUnauthorized();
    }

    public function test_like_route_requires_authentication(): void
    {
        $this->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertUnauthorized();
    }

    public function test_like_throws_404_error_if_post_not_found(): void
    {
        $nonExistsPostUuid = Str::uuid()->toString();
        
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $nonExistsPostUuid]))
            ->assertNotFound();
    }

    public function test_like_UpdateLikesCountCacheJob_queue_does_not_run_when_queue_middleware_lock_is_held(): void
    {
        $cacheValue = 10;

        // Put initial cache value ($this->post has $cacheValue 'likes_count' cache)
        Cache::put($this->postLikesCountCacheKey, $cacheValue, now()->addSeconds(10));

        $job = new UpdateLikesCountCacheJob($this->post, 1);

        // Get lock key used by the job's middleware.
        $middleware = $job->middleware()[0];

        // Lock the job's middleware lock (WithoutOverlapping) for 10 seconds
        $lock = Cache::lock($middleware->getLockKey($job), 10);

        $this->assertTrue($lock->get());

        try {
            // Run through Laravel's queue middleware pipeline.
            Bus::dispatchSync($job);

            // The job should not increment the count while locked.
            $this->assertSame($cacheValue, (int) Cache::get($this->postLikesCountCacheKey, 0));
        } finally {
            $lock->release();

            // Remove the cache
            Cache::forget($this->postLikesCountCacheKey);
        }
    }

    public function test_like_will_create_new_notification_after_pass_group_gap_internal(): void
    {
        // $this->otherUser will be the first user to like the post
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class, fn (CreatePostLikeTypeOrAttachNewUserJob $job) => $job->handle());

        // First user of the group
        Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);

        // Verify the tables
        $this->assertDatabaseCount('post_like', 1)
            ->assertDatabaseCount('notification_user', 1)
            ->assertDatabaseCount(Notification::class, 1);

        // Travel X minutes
        $this->travel(config('custom.notification.group_gap_interval'))->minutes();

        // Get another like
        $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

        $this->actingAs($newUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class)->last()->handle();

        $this->assertDatabaseCount('post_like', 2) // First like + $newUser
            ->assertDatabaseCount('notification_user', 2)
            ->assertDatabaseCount(Notification::class, 2); // 2 Because the first notification has X minutes interval with the second
    }

    public function test_like_will_dispatch_queues(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        Queue::assertPushed(UpdateLikesCountCacheJob::class, function (UpdateLikesCountCacheJob $job) {
            return $job->post->id === $this->post->id
                && $job->count === 1;
        });

        Queue::assertPushed(CreatePostLikeTypeOrAttachNewUserJob::class, function (CreatePostLikeTypeOrAttachNewUserJob $job) {
            return $job->user->id === $this->otherUser->id
                && $job->post->id === $this->post->id;
        });

        // Process the job (To see if child job dispatched or not)
        Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class)->last()->handle();

        // The child job
        Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
    }

    public function test_like_will_group_users_in_one_notification_if_followed_by_multiple_users_within_short_interval(): void
    {
        for ($index = 0; $index < $this->loopCount; $index++) {
            $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

            $this->actingAs($newUser, 'web')
                ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
                ->assertNoContent();

            Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class)->last()->handle();

            if ($index === 0) { // First user of the notification, so no need to update groups_count
                Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
            } else {
                Queue::assertPushed(UpdateGroupsCountCacheJob::class);
            }
        }

        $this->assertDatabaseCount(Notification::class, 1)
            ->assertDatabaseCount('notification_user', $this->loopCount);
    }

    public function test_like_will_not_dispatch_create_notification_queue_if_liked_by_post_owner(): void
    {
        $this->actingAs($this->user, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        Queue::assertNotPushed(CreatePostLikeTypeOrAttachNewUserJob::class);
    }

    public function test_like_will_not_dispatch_queues_if_post_already_liked(): void
    {
        $this->post->likes()->attach($this->otherUser->id);

        for ($index = 0; $index < $this->loopCount; $index++) {
            $this->actingAs($this->otherUser, 'web')
                ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
                ->assertNoContent();
        }

        Queue::assertNothingPushed();
    }

    public function test_like_will_not_dispatch_queues_if_post_deleted(): void
    {
        // Soft delete the post
        $this->post->delete();

        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        Queue::assertNothingPushed();
    }

    public function test_like_will_not_store_like_record_if_post_deleted(): void
    {
        // Soft delete the post
        $this->post->delete();

        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        $this->assertDatabaseMissing('post_like', [
            'post_id' => $this->post->id,
            'user_id' => $this->otherUser->id
        ]);
    }

    /**
     * Note: Time travel will not work if CACHE_STORE has value 'redis'.
     */
    public function test_like_will_reinitiate_post_likes_count_cache_after_pass_cache_ttl_duration(): void
    {
        config(['cache.default' => 'array']);

        // $this->otherUser will be the first user to like the post
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateLikesCountCacheJob::class, fn (UpdateLikesCountCacheJob $job) => $job->handle());

        // Ensures cache exists after being liked by $this->otherUser
        $this->assertTrue(Cache::has($this->postLikesCountCacheKey));

        // Travel X seconds
        $this->travel(config('custom.cache.post_stat.ttl_duration'))->seconds();

        // Ensures cache expired
        $this->assertFalse(Cache::has($this->postLikesCountCacheKey));

        $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

        $this->actingAs($newUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(UpdateLikesCountCacheJob::class, fn (UpdateLikesCountCacheJob $job) => $job->handle());

        // Ensures cache reinitiated
        $this->assertTrue(Cache::has($this->postLikesCountCacheKey));
    }

    public function test_like_will_store_new_notification_to_cache(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        // Process the job (To see if cache created)
        Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class, fn (CreatePostLikeTypeOrAttachNewUserJob $job) => $job->handle());

        $this->assertTrue(Cache::has($this->postLatestLikesNotificationCacheKey));

        $notification = Cache::get($this->postLatestLikesNotificationCacheKey, null);

        $this->assertTrue($notification instanceof Notification);
    }

    public function test_like_will_store_new_notification_to_cache_after_pass_group_gap_internal(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        // Process the job (To see if cache created)
        Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class, fn (CreatePostLikeTypeOrAttachNewUserJob $job) => $job->handle());

        $firstNotification = Cache::get($this->postLatestLikesNotificationCacheKey, null);

        $this->assertTrue(isset($firstNotification) && $firstNotification instanceof Notification);

        // Travel X minutes
        $this->travel(config('custom.notification.group_gap_interval'))->minutes();

        // New user drop a like
        $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

        $this->actingAs($newUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class, fn (CreatePostLikeTypeOrAttachNewUserJob $job) => $job->handle());

        $newNotification = Cache::get($this->postLatestLikesNotificationCacheKey, null);

        $this->assertTrue(isset($newNotification) && $newNotification instanceof Notification);

        // Compare
        $this->assertFalse($firstNotification->id === $newNotification->id);
    }

    public function test_like_will_store_post_likes_count_to_cache(): void
    {
        $this->actingAs($this->otherUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        // Process the job (To see if cache created)
        Queue::pushed(UpdateLikesCountCacheJob::class, fn (UpdateLikesCountCacheJob $job) => $job->handle());

        $this->assertSame(1, (int) Cache::get($this->postLikesCountCacheKey, 0));
    }

    public function test_like_will_update_notifications_groups_count_cache(): void
    {
        for ($index = 0; $index < $this->loopCount; $index++) {
            $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

            $this->actingAs($newUser, 'web')
                ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
                ->assertNoContent();

            // Process the job for notification
            Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class)->last()->handle();

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

            // Ensures $this->user (Post owner) notifications_count cache progressively incremented
            $this->assertSame(($index + 1), (int) Cache::get($this->userNotificationsCountCacheKey, 0));
        }

        $notification = $this->user->notifications()->first();

        $groupsCountCacheKey = str_replace('*', $notification->uuid, config('custom.cache.notification.pattern.groups_count'));

        // Ensures $notification groups_count cache match with $this->loopCount
        $this->assertSame($this->loopCount, (int) Cache::get($groupsCountCacheKey, 0));
    }

    public function test_like_will_update_post_likes_count_cache(): void
    {
        for ($index = 0; $index < $this->loopCount; $index++) {
            $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

            $this->actingAs($newUser, 'web')
                ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
                ->assertNoContent();

            // Process the job
            Queue::pushed(UpdateLikesCountCacheJob::class)->last()->handle();

            // Ensures $this->post likes_count cache progressively incremented
            $this->assertSame(($index + 1), (int) Cache::get($this->postLikesCountCacheKey, 0));
        }

        // Ensures $this->post likes_count cache match with $this->loopCount
        $this->assertSame($this->loopCount, (int) Cache::get($this->postLikesCountCacheKey, 0));
    }

    public function test_like_will_update_user_notifications_count_cache(): void
    {
        for ($index = 0; $index < $this->loopCount; $index++) {
            $newUser = User::factory()->has(UserStat::factory(), 'stats')->create();

            $this->actingAs($newUser, 'web')
                ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
                ->assertNoContent();

            // Process the job for notification
            Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class)->last()->handle();

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

            // Ensures $this->user (Post owner) notifications_count cache progressively incremented
            $this->assertSame(($index + 1), (int) Cache::get($this->userNotificationsCountCacheKey, 0));
        }

        // Ensures $notification groups_count cache match with $this->loopCount
        $this->assertSame($this->loopCount, (int) Cache::get($this->userNotificationsCountCacheKey, 0));
    }

    // @todo: add more tests
}
