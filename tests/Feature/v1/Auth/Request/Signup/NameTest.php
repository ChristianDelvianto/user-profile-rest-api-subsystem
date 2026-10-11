<?php

namespace Tests\Feature\v1\Auth\Request\Signup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Override;
use Tests\TestCase;

class NameTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected string $email = 'test_user@example.com';
    protected string $password = 'password';
    protected string $username = 'test_user';
    protected int $userNameMaxLength;
    protected int $userNameMinLength;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->userNameMaxLength = config('custom.user.name.max_length');
        $this->userNameMinLength = config('custom.user.name.min_length');
    }

    public function test_name_input_allow_duplication(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);

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

    public function test_name_input_with_array_value_will_throw_422_error(): void
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

    public function test_name_input_with_exact_max_character_length_will_pass(): void
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

    public function test_name_input_with_image_file_value_will_throw_422_error(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()
            ->image('test_image.jpg')
            ->size(1024);

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

    public function test_name_input_with_no_key_will_throw_422_error(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name');
    }

    public function test_name_input_with_no_value_will_throw_422_error(): void
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

    public function test_name_input_with_spaces_only_will_throw_422_error(): void
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

    public function test_name_input_with_valid_name_but_has_more_than_max_character_length_will_throw_422_error(): void
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

    public function test_name_input_with_valid_name_but_has_less_than_min_character_length_will_throw_422_error(): void
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

    public function test_name_input_with_valid_name_but_has_spaces_at_the_beginning_will_pass(): void
    {
        $name = '  Test User';

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

    public function test_name_input_with_valid_name_but_has_spaces_at_the_end_will_pass(): void
    {
        $name = 'Test User  ';

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

    public function test_name_input_with_valid_name_but_has_spaces_at_the_begininng_and_the_end_will_pass(): void
    {
        $name = ' Test User ';

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

    public function test_name_input_with_valid_name_will_pass(): void
    {
        $name = 'Test User';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();

        $this->assertDatabaseCount(User::class, 1);
    }

    public function test_name_input_with_invalid_characters_will_throw_422_error(): void
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
}
