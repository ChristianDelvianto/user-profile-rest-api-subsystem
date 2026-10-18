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
    protected int $userUsernameMaxLength;
    protected int $userUsernameMinLength;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

        $this->userUsernameMaxLength = config('custom.user.username.max_length');
        $this->userUsernameMinLength = config('custom.user.username.min_length');
    }

    public function test_username_input_must_be_unique(): void
    {
        $user = User::factory()->create(['username' => 'test_user']);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => strtoupper($user->username),
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('username');

        $this->assertDatabaseCount(User::class, 1);
    }

    public function test_username_input_with_array_value_will_throw_422_error(): void
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

    public function test_username_input_with_image_file_value_will_throw_422_error(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()
            ->image('test_image.jpg')
            ->size(1024);

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

    public function test_username_input_with_no_key_will_throw_422_error(): void
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

    public function test_username_input_with_no_value_will_throw_422_error(): void
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

    public function test_username_input_with_spaces_only_will_throw_422_error(): void
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

    public function test_username_input_with_valid_username_and_has_max_character_length_will_pass(): void
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

    public function test_username_input_with_valid_username_but_has_less_than_min_character_length_will_throw_422_error(): void
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

    public function test_username_input_with_valid_username_but_has_more_than_max_character_length_will_throw_422_error(): void
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

    public function test_username_input_with_valid_username_but_has_spaces_at_the_beginning_will_pass(): void
    {
        $username = '  test_user';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['username' => trim($username)]);
    }

    public function test_username_input_with_valid_username_but_has_spaces_at_the_end_will_pass(): void
    {
        $username = 'test_user  ';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['username' => trim($username)]);
    }

    public function test_username_input_with_valid_username_will_pass(): void
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
        ];

        foreach ($usernames as $username) {
            $this->postJson(route('api.v1.auth.signup'), [
                    'username' => $username,
                    'name' => $this->name,
                    'email' => fake()->email(),
                    'password' => $this->password,
                    'password_confirmation' => $this->password
                ])
                ->assertNoContent();

            $this->assertDatabaseHas(User::class, ['username' => $username]);
        }
    }

    public function test_username_input_with_invalid_characters_will_throw_422_error(): void
    {
        $usernames = [
            '@test_user',
            'she\'s mine!',
            'r/codingcareer',
            '#meme',
            'One < Three',
            'This is ,',
            'This is . (dot)',
            'Testing,1,2,3',
            'Testing, 1, 2, 3'
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
