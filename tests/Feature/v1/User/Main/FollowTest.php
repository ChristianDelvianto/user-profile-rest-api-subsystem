<?php

namespace Tests\Feature\v1\User\Main;

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
            ->assertDatabaseHas('user_follow', [
                'follower_id' => $follower->id,
                'user_id' => $this->user->id
            ]);
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
