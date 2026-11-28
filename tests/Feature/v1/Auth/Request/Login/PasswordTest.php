<?php

namespace Tests\Feature\v1\Auth\Request\Login;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Override;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $email = 'test_user@example.com';
    protected string $name = 'Test User';
    protected string $password = 'password';
    protected string $username = 'test_user';
    protected User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders(['Accept' => 'application/json', 'Origin' => config('app.url')]);

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

        $this->user = User::factory()->create(['username' => $this->username, 'name' => $this->name, 'email' => $this->email, 'password' => $this->password]);
    }

    public function test_password_input_is_valid_although_the_value_reached_max_character_length(): void
    {
        $password = '';
        $characterLength = 255;

        for ($index = 0; $index < $characterLength; $index++) {
            $password .= 'a';
        }

        // Create custom user that has password length 255
        $user = User::factory()->create(['password' => $password]);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $user->email,
                'password' => $password
            ])
            ->assertOk();
    }

    public function test_password_input_is_valid_if_the_value_has_valid_password(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $this->password
            ])
            ->assertOk();
    }

    public function test_password_input_throws_422_error_if_the_value_has_character_length_less_than_8(): void
    {
        $shortPassword = substr($this->password, 0, 7);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $shortPassword
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_the_value_has_character_length_more_than_255(): void
    {
        $password = '';
        $characterLength = 256;

        for ($index = 0; $index < $characterLength; $index++) {
            $password .= 'a';
        }

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_the_value_is_an_array(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => ['abc', 'def']
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_the_value_is_an_empty_string(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => ''
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_the_value_is_a_file(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()->image('test_image.jpg')->size(1024);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $fakeImage
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_no_key_for_the_input(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_the_value_is_invalid(): void
    {
        $invalidPassword = $this->password .= '123';

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $invalidPassword
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    // We are using 'email' key when failed to authenticate, see \v1\Auth\LoginController
    public function test_password_input_will_use_email_error_key_when_failed_to_authenticate(): void
    {
        $invalidPassword = $this->password .= '123';

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $invalidPassword
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }
}
