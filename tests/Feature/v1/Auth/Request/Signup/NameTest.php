<?php

namespace Tests\Feature\v1\Auth\Request\Signup;

use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Override;
use Tests\TestCase;

class NameTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $email = 'test_user@example.com';
    protected string $name = 'Test User';
    protected string $password = 'password';
    protected string $username = 'test_user';
    protected int $userNameMaxLength;
    protected int $userNameMinLength;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders(['Accept' => 'application/json', 'Origin' => config('app.url')]);

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

        $this->userNameMaxLength = config('custom.user.name.max_length');
        $this->userNameMinLength = config('custom.user.name.min_length');
    }

    public function test_name_input_allow_duplicates(): void
    {
        $user = User::factory()->has(UserStat::factory(), 'stats')->create(['name' => $this->name]);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $user->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseCount(User::class, 2);
    }

    // Laravel will trim the spaces
    public function test_name_input_is_valid_although_the_value_has_spaces_at_the_beginning(): void
    {
        $name = '  ' . $this->name;

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['name' => trim($name)]);
    }

    // Laravel will trim the spaces
    public function test_name_input_is_valid_although_the_value_has_spaces_at_the_end(): void
    {
        $name = $this->name . '  ';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['name' => trim($name)]);
    }

    // Laravel will trim the spaces
    public function test_name_input_is_valid_although_the_value_has_spaces_at_the_begininng_and_the_end(): void
    {
        $name = '  ' . $this->name . ' ';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['name' => trim($name)]);
    }

    public function test_name_input_is_valid_although_the_value_reached_max_character_length(): void
    {
        $name = '';

        for ($index = 0; $index < $this->userNameMaxLength; $index++) {
            $name .= 'a';
        }

        $this->assertTrue(strlen($name) === $this->userNameMaxLength);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(User::class, ['name' => $name]);
    }

    public function test_name_input_is_valid_if_the_value_has_valid_name(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseCount(User::class, 1);
    }

    public function test_name_input_throws_422_error_if_no_key_for_the_input(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name');
    }

    public function test_name_input_throws_422_error_if_the_value_contain_invalid_characters(): void
    {
        $names = [
            'ab 12!@_$',
            'Dr. John Doe',
            'She is my ___',
            'We... ',
            'Weird Pte. Ltd.'
        ];

        foreach ($names as $name) {
            $this->postJson(route('api.v1.auth.signup'), [
                    'username' => $this->username,
                    'name' => $name,
                    'email' => $this->email,
                    'password' => $this->password,
                    'password_confirmation' => $this->password
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrorFor('name');
        }
    }

    public function test_name_input_throws_422_error_if_the_value_contain_only_spaces(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => '  ',
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name');
    }

    public function test_name_input_throws_422_error_if_the_value_has_character_length_less_than_X(): void
    {
        $limit = $this->userNameMinLength - 1;
        $name = '';

        for ($index = 0; $index < $limit; $index++) {
            $name .= 'a';
        }

        $this->assertTrue(strlen($name) < $this->userNameMinLength);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name');
    }

    public function test_name_input_throws_422_error_if_the_value_has_character_length_more_than_255(): void
    {
        $limit = $this->userNameMaxLength + 1;
        $name = '';

        for ($index = 0; $index < $limit; $index++) {
            $name .= 'a';
        }

        $this->assertTrue(strlen($name) > $this->userNameMaxLength);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name');
    }

    public function test_name_input_throws_422_error_if_the_value_is_an_array(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => ['abc', 'def'],
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name');
    }

    public function test_name_input_throws_422_error_if_the_value_is_an_empty_string(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => '',
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name');
    }

    public function test_name_input_throws_422_error_if_the_value_is_a_file(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()->image('test_image.jpg')->size(1024);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $fakeImage,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name');
    }
}
