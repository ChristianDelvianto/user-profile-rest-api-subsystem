<?php

namespace Tests\Feature\v1\Auth\Request\Signup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $name = 'Test User';
    protected string $password = 'password';

    public function test_password_input_with_array_value_will_throw_422_error(): void
    {
        $password = ['abc', 'def'];

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password' => $password,
                'password_confirmation' => $password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_character_length_less_than_min_length_required_will_throw_422_error(): void
    {
        $shortPassword = substr($this->password, 0, 7);

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password' => $shortPassword,
                'password_confirmation' => $shortPassword
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

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password' => $password,
                'password_confirmation' => $password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_different_password_confirmation_will_throw_422_error(): void
    {
        $differentPassword = $this->password;
        $differentPassword .= '1';

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password' => $this->password,
                'password_confirmation' => $differentPassword
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

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password' => $password,
                'password_confirmation' => $password
            ])
            ->assertNoContent();
    }

    public function test_password_input_with_image_file_value_will_throw_422_error(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()
            ->image('test_image.jpg')
            ->size(1024);

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password' => $fakeImage,
                'password_confirmation' => $fakeImage
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_no_main_password_key_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_no_main_password_value_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password' => '',
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_no_password_confirmation_key_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_no_password_confirmation_value_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password' => $this->password,
                'password_confirmation' => ''
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_with_valid_password_will_pass(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => fake()->email(),
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();
    }
}
