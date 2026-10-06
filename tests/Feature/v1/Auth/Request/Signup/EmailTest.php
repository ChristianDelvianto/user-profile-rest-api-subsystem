<?php

namespace Tests\Feature\v1\Auth\Request\Signup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $password = 'password';

    public function test_email_input_must_be_unique(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => fake()->name(),
                'email' => $user->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');

        $this->assertDatabaseCount(User::class, 1);
    }

    public function test_email_input_with_array_value_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'name' => fake()->name(),
                'email' => ['abc', 'def'],
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_with_image_file_value_will_throw_422_error(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()
            ->image('test_image.jpg')
            ->size(1024);

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => fake()->name(),
                'email' => $fakeImage,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_with_no_key_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'name' => fake()->email(),
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_with_no_value_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'name' => fake()->name(),
                'email' => '',
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_with_plain_string_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'name' => fake()->name(),
                'email' => 'abcde',
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_with_valid_email_and_has_max_character_length_will_pass(): void
    {
        $limit = 255;
        $email = '';
        $emailSuffix = '@example.com';
        $characterLength = $limit - strlen($emailSuffix);

        for ($index = 0; $index < $characterLength; $index++) {
            $email .= 'a';
        }

        $email .= $emailSuffix;

        $this->assertTrue(strlen($email) === 255);

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => fake()->name(),
                'email' => $email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['email' => $email]);
    }

    public function test_email_input_with_valid_email_but_has_more_than_255_character_length_will_throw_422_error(): void
    {
        $email = '';
        $characterLength = 256;

        for ($index = 0; $index < $characterLength; $index++) {
            $email .= 'a';
        }

        $email .= '@example.com';

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => fake()->name(),
                'email' => $email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_with_valid_email_will_pass(): void
    {
        $email = fake()->email();

        $this->postJson(route('api.v1.auth.signup'), [
                'name' => fake()->name(),
                'email' => $email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['email' => $email]);
    }
}
