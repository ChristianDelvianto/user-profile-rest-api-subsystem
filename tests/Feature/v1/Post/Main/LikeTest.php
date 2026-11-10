<?php

namespace Tests\Feature\v1\Post\Main;

use App\Enums\NotificationType;
use App\Jobs\Notification\Post\CreatePostLikeTypeOrAttachNewUserJob;
use App\Jobs\Notification\UpdateGroupsCountCacheJob;
use App\Jobs\Post\UpdateLikesCountCacheJob;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostStat;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Override;
use Tests\TestCase;

class LikeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Post $post;
    protected User $user;
    protected string $postLikesCountCacheKey;
    protected string $userNotificationsCountCacheKey;

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

        $this->post = Post::factory()
                    ->has(PostStat::factory(), 'stats')
                    ->create(['user_id' => $this->user->id]);

        $this->postLikesCountCacheKey = str_replace('*', $this->post->id, config('custom.cache.post_stat.pattern.likes_count'));
        $this->userNotificationsCountCacheKey = str_replace('*', $this->user->id, config('custom.cache.user_stat.pattern.notifications_count'));
    }

    public function test_can_like_successfully(): void
    {
        $newUser = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->actingAs($newUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        Queue::assertPushed(CreatePostLikeTypeOrAttachNewUserJob::class);

        Queue::assertPushed(UpdateLikesCountCacheJob::class);

        // Process the job
        Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class, fn (CreatePostLikeTypeOrAttachNewUserJob $job) => $job->handle());

        // Ensure UpdateGroupsCountCacheJob not pushed (first user of the notification)
        Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);

        $this->assertDatabaseCount('post_like', 1)
            ->assertDatabaseCount('notification_user', 1)
            ->assertDatabaseHas('post_like', [
                'post_id' => $this->post->id,
                'user_id' => $newUser->id
            ])
            ->assertDatabaseHas(Notification::class, [
                'type' => NotificationType::PostLike->value,
                'groups_count' => 1,
                'post_id' => $this->post->id,
                'user_id' => $this->user->id
            ]);
    }

    public function test_like_after_X_minutes_will_create_new_notification(): void
    {
        $firstUser = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->actingAs($firstUser, 'web')
            ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
            ->assertNoContent();

        // Process the job
        Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class, fn (CreatePostLikeTypeOrAttachNewUserJob $job) => $job->handle());

        $this->assertDatabaseCount('post_like', 1)
            ->assertDatabaseCount('notification_user', 1)
            ->assertDatabaseCount(Notification::class, 1);

        // Travel X minutes
        $this->travel(config('custom.notification.group_gap_interval'))->minutes();

        $newUsersCount = 10;

        // Get 10 more likes
        for ($index = 0; $index < $newUsersCount; $index++) {
            $newUser = User::factory()
                        ->has(UserStat::factory(), 'stats')
                        ->create();

            $this->actingAs($newUser, 'web')
                ->postJson(route('api.v1.post.like', ['post' => $this->post->uuid]))
                ->assertNoContent();

            $this->assertDatabaseHas('post_like', [
                'post_id' => $this->post->id,
                'user_id' => $newUser->id,
            ]);

            // Process the job (Must use last() here)
            Queue::pushed(CreatePostLikeTypeOrAttachNewUserJob::class)->last()->handle();

            if ($index === 0) {
                // Assert no other users belong to the notification, because its first user of the group
                Queue::assertNotPushed(UpdateGroupsCountCacheJob::class);
            } else {
                Queue::assertPushed(UpdateGroupsCountCacheJob::class);
            }
        }

        $this->assertDatabaseCount('post_like', 11) // First like + $newUsersCount (10)
            ->assertDatabaseCount('notification_user', 11)
            ->assertDatabaseCount(Notification::class, 2); // 2 Because the first notification has X minutes interval with the second
    }

    // @todo: add more tests
}
