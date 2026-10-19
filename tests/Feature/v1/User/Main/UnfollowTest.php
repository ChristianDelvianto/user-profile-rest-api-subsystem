<?php

namespace Tests\Feature\v1\User\Main;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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

        $this->user = User::factory()->create();
    }

    public function test_can_unfollow_successfully(): void
    {
        $follower = User::factory()->create();

        $this->actingAs($follower, 'web')
            ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
            ->assertNoContent();

        // User decided to unfollow, in real-life scenario user could be misclicked; 'back to zero'
        $this->travel(2)->seconds();

        $this->actingAs($follower, 'web')
            ->deleteJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertNoContent();

        $this->assertDatabaseCount('user_follow', 0)
            ->assertDatabaseCount('notification_user', 0)
            ->assertDatabaseMissing('user_follow', [
                'follower_id' => $follower->id,
                'user_id' => $this->user->id
            ])
            ->assertDatabaseHas(Notification::class, [
                'groups_count' => 0,
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

    public function test_unfollow_route_exists(): void
    {
        $this->postJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertUnauthorized();
    }

    public function test_unfollow_route_requires_authentication(): void
    {
        $this->postJson(route('api.v1.user.unfollow', ['user' => $this->user->id]))
            ->assertUnauthorized();
    }
}
