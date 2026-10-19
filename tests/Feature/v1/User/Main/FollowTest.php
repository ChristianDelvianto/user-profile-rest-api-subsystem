<?php

namespace Tests\Feature\v1\User\Main;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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

        $this->user = User::factory()->create();
    }

    public function test_can_follow_successfully(): void
    {
        $follower = User::factory()->create();

        $this->actingAs($follower, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        $this->assertDatabaseCount('user_follow', 1)
            ->assertDatabaseHas('notification_user', [
                'user_id' => $follower->id
            ])
            ->assertDatabaseHas('user_follow', [
                'follower_id' => $follower->id,
                'user_id' => $this->user->id
            ])
            ->assertDatabaseHas(Notification::class, [
                'type' => NotificationType::AccountFollower,
                'user_id' => $this->user->id
            ]);
    }

    public function test_follow_from_multiple_users_within_short_interval_will_group_the_users(): void
    {
        $loopCount = 10;

        for ($index = 0; $index < $loopCount; $index++) {
            $follower = User::factory()->create();

            $this->actingAs($follower, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            $this->assertDatabaseHas('notification_user', ['user_id' => $follower->id]);
        }

        $this->assertDatabaseCount('user_follow', $loopCount)
            ->assertDatabaseCount('notification_user', $loopCount)
            ->assertDatabaseCount(Notification::class, 1)
            ->assertDatabaseHas(Notification::class, [
                'type' => NotificationType::AccountFollower,
                'groups_count' => $loopCount,
                'user_id' => $this->user->id
            ]);
    }

    public function test_follow_after_certain_interval_will_create_new_notification(): void
    {
        // First follower
        $follower = User::factory()->create();

        $this->actingAs($follower, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // Travel X minutes
        $this->travel(config('custom.notification.group_gap_interval') * 60)->seconds();

        // Got 10 more followers
        for ($index = 0; $index < 10; $index++) {
            $follower = User::factory()->create();

            $this->actingAs($follower, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            $this->assertDatabaseHas('notification_user', ['user_id' => $follower->id]);
        }

        $this->assertDatabaseCount('user_follow', 11) // First follower + 10 new followers
            ->assertDatabaseCount('notification_user', 11) // First follower + 10 new followers
            ->assertDatabaseCount(Notification::class, 2); // Notification from first followers, then grouped notification from 10 new followers
    }

    public function test_follow_idempotency(): void
    {
        $follower = User::factory()->create();

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
}
