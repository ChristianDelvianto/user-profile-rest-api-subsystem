<?php

namespace Tests\Feature\v1\Auth\Main;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Override;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);
    }

    public function test_can_logout_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->postJson(route('api.v1.auth.logout'))
            ->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_logout_route_exists(): void
    {
        $this->postJson(route('api.v1.auth.logout'))
            ->assertUnauthorized();
    }

    public function test_logout_route_requires_authentication(): void
    {
        $this->postJson(route('api.v1.auth.logout'))
            ->assertUnauthorized();
    }
}
