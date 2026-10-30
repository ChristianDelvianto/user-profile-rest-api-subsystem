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

        // This line is mandatory, otherwise the tests will throw 419 error
        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => config('app.url')
        ]);

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);
    }

    public function test_email_input_with_array_value_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => ['abc', 'def'],
                'password' => $this->password
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

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $fakeImage,
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_with_no_key_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_with_no_value_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => '',
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_with_plain_string_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => 'abcde',
                'password' => $this->password
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

        // Create custom user with special email
        $user = User::factory()->create(['email' => $email]);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $email,
                'password' => $this->password
            ])
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_input_with_valid_email_but_has_more_than_255_character_length_will_throw_422_error(): void
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

    public function test_email_input_with_valid_email_but_no_user_account_related_with_it_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.login'), [
                'email' => 'no_user@example.com',
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_with_valid_email_will_pass(): void
    {
        $user = User::factory()->create(['password' => $this->password]);

        $this->postJson(route('api.v1.auth.login'), [
                'email' => $user->email,
                'password' => $this->password
            ])
            ->assertOk();
    }
}
