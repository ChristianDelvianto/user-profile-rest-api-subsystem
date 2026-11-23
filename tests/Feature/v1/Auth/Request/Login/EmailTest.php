<?php

namespace Tests\Feature\v1\Auth\Request\Login;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Override;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $password = 'password';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders(['Accept' => 'application/json', 'Origin' => config('app.url')]);

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);
    }

    public function test_email_input_is_valid_although_the_value_reached_max_character_length(): void
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

        // Create custom user with special email
        $user = User::factory()->create(['email' => $email]);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $email,
                'password' => $this->password
            ])
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_input_is_valid_if_the_value_has_valid_email(): void
    {
        $user = User::factory()->create(['password' => $this->password]);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $user->email,
                'password' => $this->password
            ])
            ->assertOk();
    }

    public function test_email_input_throws_422_error_if_no_key_for_the_input(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_throws_422_error_if_the_value_is_an_array(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => ['abc', 'def'],
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_throws_422_error_if_the_value_is_a_file(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()->image('test_image.jpg')->size(1024);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $fakeImage,
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_throws_422_error_if_the_value_is_an_empty_string(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => '',
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_throws_422_error_if_the_value_is_a_plain_string(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => 'abcde',
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_throws_422_error_if_the_value_has_character_length_more_than_255(): void
    {
        $email = '';
        $characterLength = 256;

        for ($index = 0; $index < $characterLength; $index++) {
            $email .= 'a';
        }

        $email .= '@example.com';

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $email,
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_throws_422_error_if_no_user_related_to_the_email(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => 'no_user@example.com',
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }
}
