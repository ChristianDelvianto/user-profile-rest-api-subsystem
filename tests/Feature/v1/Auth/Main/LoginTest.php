<?php

namespace Tests\Feature\v1\Auth\Main;

use App\Http\RateLimiters\v1\LoginRateLimiter;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
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

        $this->user = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();
    }

    public function test_can_login_successfully(): void
    {
        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

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
        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

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
        $user = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->create();

        // Relogin with different user
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $user->email,
                'password' => $this->password
            ])
            ->assertOk();

        $this->assertAuthenticated('web')
            ->assertAuthenticatedAs($user);
    }

    public function test_login_as_authenticated_user_will_throw_403_error(): void
    {
        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

        $user = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->create();

        $this->actingAs($this->user, 'web')
            ->postJson(route('api.v1.auth.login'), [
                'email' => $user->email,
                'password' => 'password'
            ])
            ->assertForbidden();

        $this->assertAuthenticatedAs($this->user, 'web');
    }

    public function test_login_attempts_more_than_X_times_will_trigger_throttle_guard(): void
    {
        $maxAttempts = config('custom.throttle.api.v1.login.max_attempts');

        $loginLimiter = app(LoginRateLimiter::class);

        $requestIp = '215.0.32.1';

        try {
            // Attempts to login 10 times
            for ($index = 0; $index < 10; $index++) {
                if ($index < $maxAttempts) {
                    $this->withServerVariables([
                            'REMOTE_ADDR' => $requestIp
                        ])
                        ->postJson(route('api.v1.auth.login'), [
                            'email' => $this->user->email,
                            'password' => 'invalid_password'
                        ])
                        ->assertUnprocessable();
                } else {
                    $this->withServerVariables([
                            'REMOTE_ADDR' => $requestIp
                        ])
                        ->postJson(route('api.v1.auth.login'), [
                            'email' => $this->user->email,
                            'password' => 'invalid_password'
                        ])
                        ->assertTooManyRequests();
                }
            }

            // Verify throttle is working
            $this->assertTrue($loginLimiter->isBlocked($requestIp, $this->user->email));
        } finally {
            // Clear the throttle so other test that using same ip and same email could work
            $loginLimiter->clear($requestIp, $this->user->email);
        }

        // Verify the throttle has been removed
        $this->assertFalse($loginLimiter->isBlocked($requestIp, $this->user->email));
    }

    public function test_login_route_exists(): void
    {
        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

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

    public function test_login_with_different_ip_will_authenticate(): void
    {
        $maxAttempts = config('custom.throttle.api.v1.login.max_attempts');

        $loginLimiter = app(LoginRateLimiter::class);

        $requestIp1 = '215.0.32.1';
        $requestIp2 = '215.0.32.2';

        $formData = ['email' => $this->user->email, 'password' => 'invalid_password'];

        // Attempts to login 10 times
        for ($index = 0; $index < 10; $index++) {
            if ($index < $maxAttempts) {
                $this->withServerVariables([
                        'REMOTE_ADDR' => $requestIp1
                    ])
                    ->postJson(route('api.v1.auth.login'), $formData)
                    ->assertUnprocessable();
            } else {
                $this->withServerVariables([
                        'REMOTE_ADDR' => $requestIp1
                    ])
                    ->postJson(route('api.v1.auth.login'), $formData)
                    ->assertTooManyRequests();
            }
        }

        // Verify throttle is working
        $this->assertTrue($loginLimiter->isBlocked($requestIp1, $this->user->email));

        // Login with different IP
        $this->withServerVariables([
                'REMOTE_ADDR' => $requestIp2
            ])
            ->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $this->password
            ])
            ->assertOk();

        $this->assertAuthenticatedAs($this->user);

        // Clear the throttle, so other tests will not throw 429 error
        $loginLimiter->clear($requestIp1, $this->user->email);

        // Verify the throttle has been removed
        $this->assertFalse($loginLimiter->isBlocked($requestIp1, $this->user->email));
    }
}
