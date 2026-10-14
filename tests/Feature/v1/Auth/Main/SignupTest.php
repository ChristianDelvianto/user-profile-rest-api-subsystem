<?php

namespace Tests\Feature\v1\Auth\Main;

use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SignupTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $email = 'test@example.com';
    protected string $name = 'Test User';
    protected string $password = 'password';
    protected string $username = 'test_user';

    public function test_can_signup_successfully(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['email' => $this->email]);
    }

    public function test_signup_route_exists(): void
    {
        $this->postJson(route('api.v1.auth.signup'))
            ->assertUnprocessable();
    }

    public function test_signup_successfully_will_create_user_stats(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $user = User::firstWhere('email', $this->email);

        $this->assertDatabaseHas(UserStat::class, ['user_id' => $user->id]);
    }

    public function test_signup_successfully_will_hash_password_using_argon2id(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['email' => $this->email]);

        $user = User::where('email', $this->email)->first();

        $this->assertSame('argon2id', password_get_info($user->password)['algoName']);

        $this->assertTrue(Hash::check($this->password, $user->password));
    }
}
