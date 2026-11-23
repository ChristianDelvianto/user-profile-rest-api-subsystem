<?php

namespace Tests\Feature\v1\Auth\Request\Signup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Override;
use Tests\TestCase;

class UsernameTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $email = 'test_user@example.com';
    protected string $name = 'Test User';
    protected string $password = 'password';
    protected string $username = 'test_user';
    protected int $userUsernameMaxLength;
    protected int $userUsernameMinLength;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders(['Accept' => 'application/json', 'Origin' => config('app.url')]);

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

        $this->userUsernameMaxLength = config('custom.user.username.max_length');
        $this->userUsernameMinLength = config('custom.user.username.min_length');
    }

    // Laravel will trim the spaces
    public function test_username_input_is_valid_although_the_value_has_spaces_at_the_beginning(): void
    {
        $username = '  ' . $this->username;

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseCount(User::class, 1)
            ->assertDatabaseHas(User::class, ['username' => trim($username)])
            ->assertDatabaseHas(User::class, ['username' => $this->username]);
    }

    // Laravel will trim the spaces
    public function test_username_input_is_valid_although_the_value_has_spaces_at_the_end(): void
    {
        $username = $this->username . '  ';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseCount(User::class, 1)
            ->assertDatabaseHas(User::class, ['username' => trim($username)])
            ->assertDatabaseHas(User::class, ['username' => $this->username]);
    }

    // Laravel will trim the spaces
    public function test_username_input_is_valid_although_the_value_has_spaces_at_the_begininng_and_the_end(): void
    {
        $username = '  ' . $this->username . '  ';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseCount(User::class, 1)
            ->assertDatabaseHas(User::class, ['username' => trim($username)])
            ->assertDatabaseHas(User::class, ['username' => $this->username]);
    }

    public function test_username_input_is_valid_although_the_value_reached_max_character_length(): void
    {
        $username = '';

        for ($index = 0; $index < $this->userUsernameMaxLength; $index++) {
            $username .= 'a';
        }

        $this->assertTrue(strlen($username) === $this->userUsernameMaxLength);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['username' => $username]);
    }

    public function test_username_input_is_valid_if_the_value_has_valid_username(): void
    {
        $usernames = [
            'test_.user',
            '.test._user.',
            'we.are.strong',
            'the.batman',
            '.the_dark_knight.',
            '1_2_3_4_5',
            '1.2.3.4.5',
            '.1.2.3.4.5.',
            '_._._._.',
            '....',
            '__.__',
            '_______',
            'invoker_.',
            '_.invoker',
            '._invoker'
        ];

        foreach ($usernames as $index => $username) {
            $email = 'test_email_' . ($index + 1) . '@example.com';

            $this->postJson(route('api.v1.auth.signup'), [
                    'username' => $username,
                    'name' => $this->name,
                    'email' => $email,
                    'password' => $this->password,
                    'password_confirmation' => $this->password
                ])
                ->assertNoContent();

            $this->assertDatabaseHas(User::class, ['username' => $username]);
        }
    }

    public function test_username_input_must_be_unique(): void
    {
        User::factory()->create(['username' => $this->username]);

        $username = strtoupper($this->username);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('username');

        $this->assertDatabaseCount(User::class, 1); // The user we created with factory
    }

    public function test_username_input_throws_422_error_if_no_key_for_the_input(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('username');
    }

    public function test_username_input_throws_422_error_if_the_value_has_character_length_less_than_X(): void
    {
        $limit = $this->userUsernameMinLength - 1;
        $username = '';

        for ($index = 0; $index < $limit; $index++) {
            $username .= 'a';
        }

        $this->assertTrue(strlen($username) < $this->userUsernameMinLength);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('username');
    }

    public function test_username_input_throws_422_error_if_the_value_has_character_length_more_than_X(): void
    {
        $limit = $this->userUsernameMaxLength + 1;
        $username = '';

        for ($index = 0; $index < $limit; $index++) {
            $username .= 'a';
        }

        $this->assertTrue(strlen($username) > $this->userUsernameMaxLength);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('username');
    }

    public function test_username_input_throws_422_error_if_the_value_is_an_array(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => ['abc', 'def'],
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('username');
    }

    public function test_username_input_throws_422_error_if_the_value_is_an_empty_string(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => '',
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('username');
    }

    public function test_username_input_throws_422_error_if_the_value_is_a_file(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()->image('test_image.jpg')->size(1024);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $fakeImage,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('username');
    }

    public function test_username_input_throws_422_error_if_the_value_contain_only_spaces(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => '     ',
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('username');
    }

    public function test_username_input_throws_422_error_if_the_value_contain_invalid_characters(): void
    {
        $usernames = [
            '@test_user',
            'r/cscareer',
            '#meme',
            'One < Three',
            'This is ,',
            'This is dot (.)',
            'Testing,1,2,3',
            'Testing, 1, 2, 3',
            'abcd!',
            'abcd-',
            'abcd='
        ];

        foreach ($usernames as $username) {
            $this->postJson(route('api.v1.auth.signup'), [
                    'username' => $username,
                    'name' => $this->name,
                    'email' => $this->email,
                    'password' => $this->password,
                    'password_confirmation' => $this->password
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrorFor('username');
        }
    }
}
