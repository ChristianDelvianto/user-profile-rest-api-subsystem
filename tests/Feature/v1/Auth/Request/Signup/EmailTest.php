<?php

namespace Tests\Feature\v1\Auth\Request\Signup;

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

    protected string $email = 'test_user@example.com';
    protected string $name = 'Test User';
    protected string $password = 'password';
    protected string $username = 'test_user';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders(['Accept' => 'application/json', 'Origin' => config('app.url')]);

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);
    }

    // Laravel will trim the spaces
    public function test_email_input_is_valid_although_the_value_has_spaces_at_the_beginning(): void
    {
        $email = '  ' . $this->email;

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseMissing(User::class, ['email' => $email])
            ->assertDatabaseHas(User::class, ['email' => trim($email)]);
    }

    // Laravel will trim the spaces
    public function test_email_input_is_valid_although_the_value_has_spaces_at_the_end(): void
    {
        $email = $this->email . '  ';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseMissing(User::class, ['email' => $email])
            ->assertDatabaseHas(User::class, ['email' => trim($email)]);
    }

    // Laravel will trim the spaces
    public function test_email_input_is_valid_although_the_value_has_spaces_at_the_begininng_and_the_end(): void
    {
        $email = '  ' . $this->email . '  ';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseMissing(User::class, ['email' => $email])
            ->assertDatabaseHas(User::class, ['email' => trim($email)]);
    }

    public function test_email_input_is_valid_if_the_value_is_a_valid_email(): void
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

    public function test_email_input_is_valid_if_the_value_is_a_valid_email_although_with_max_character_length(): void
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
                'username' => $this->username,
                'name' => $this->name,
                'email' => $email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['email' => $email]);
    }

    public function test_email_input_must_be_unique(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $user->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');

        // The uppercase version will pass (Unless we set specific rules)
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => strtoupper($user->email),
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();
    }

    public function test_email_input_throws_422_error_if_no_key_for_the_input(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'password' => $this->password,
                'password_confirmation' => $this->password
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

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_throws_422_error_if_the_value_is_an_array(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => ['abc', 'def'],
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_throws_422_error_if_the_value_is_an_empty_string(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => '',
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_throws_422_error_if_the_value_is_a_file(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()->image('test_image.jpg')->size(1024);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $fakeImage,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_email_input_throws_422_error_if_the_value_is_a_plain_string(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => 'abcde',
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }
}
