<?php

namespace Tests\Feature\v1\Auth\Main;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\Fluent\AssertableJson;
use Override;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $password = 'password';
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
                'password' => $this->password
            ])
            ->assertOk();

        $this->assertAuthenticated('web')
            ->assertAuthenticatedAs($this->user);
    }

    public function test_can_relogin_successfully(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $this->password
            ])
            ->assertOk();

        $this->assertAuthenticated('web')
            ->assertAuthenticatedAs($this->user);

        $this->postJson(route('api.v1.auth.logout'))
            ->assertNoContent();

        $this->assertGuest('web');

        // Create different user
        $user = User::factory()->create();

        // Relogin with different user
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $user->email,
                'password' => $this->password
            ])
            ->assertOk();

        $this->assertAuthenticated('web')
            ->assertAuthenticatedAs($user);
    }

    public function test_login_route_exists(): void
    {
        $this->postJson(route('api.v1.auth.login'))
            ->assertUnprocessable();
    }

    public function test_login_successfully_will_return_response_contains_expected_fields(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $this->password
            ])
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->has('is_verified')
                    ->has('profile')
                    ->etc();
            });
    }
}
