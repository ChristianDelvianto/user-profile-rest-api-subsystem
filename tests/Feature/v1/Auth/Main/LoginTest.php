<?php

namespace Tests\Feature\v1\Auth\Main;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Override;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_login_successfully(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => 'password'
            ])
            ->assertOk();

        $this->assertAuthenticated('web')
            ->assertAuthenticatedAs($this->user);
    }

    public function test_can_relogin_successfully(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => 'password'
            ])
            ->assertOk();

        $this->assertAuthenticated('web')
            ->assertAuthenticatedAs($this->user);

        $this->postJson(route('api.v1.auth.logout'))
            ->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_login_route_exists(): void
    {
        $this->postJson(route('api.v1.auth.login'))
            ->assertUnprocessable();
    }

    public function test_successful_login_response_contains_expected_fields(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => 'password'
            ])
            ->assertOk()
            ->assertJsonStructure([
                'is_verified',
                'profile'
            ]);
    }
}
