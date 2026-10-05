<?php

namespace Tests\Feature\v1\Auth\Request\Login;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Override;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $password;
    protected User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->password = 'password';
        $this->user = User::factory()->create(['password' => $this->password]);
    }

    public function test_password_input_with_array_value_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => ['abc', 'def']
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_character_length_less_than_min_length_required_will_throw_422_error(): void
    {
        $shortPassword = substr($this->password, 0, 7);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $shortPassword
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_character_length_more_than_max_length_will_throw_422_error(): void
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

    public function test_password_input_with_exact_max_character_length_will_pass(): void
    {
        $password = '';
        $characterLength = 255;

        for ($index = 0; $index < $characterLength; $index++) {
            $password .= 'a';
        }

        $this->assertTrue(strlen($password) === 255);

        // Custom user that has password length 255
        $user = User::factory()
            ->create(['password' => $password]);

        $this->assertFalse($this->user->id === $user->id);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $user->email,
                'password' => $password
            ])
            ->assertOk();
    }

    public function test_password_input_with_image_file_value_will_throw_422_error(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()
            ->image('test_image.jpg')
            ->size(1024);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $fakeImage
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    /**
     * We are using 'email' key when password invalid, see \v1\Auth\LoginController
     */
    public function test_password_input_with_invalid_password_has_email_error_key(): void
    {
        $invalidPassword = $this->password .= '123';

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $invalidPassword
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_password_input_with_invalid_password_will_throw_422_error(): void
    {
        $invalidPassword = $this->password .= '123';

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $invalidPassword
            ])
            ->assertUnprocessable();
    }

    public function test_password_input_with_min_character_length_but_has_invalid_value_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => 'passw0rd'
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_password_input_with_no_key_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_no_value_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => ''
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_valid_password_will_pass(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => $this->user->email,
                'password' => $this->password
            ])
            ->assertOk();
    }
}
