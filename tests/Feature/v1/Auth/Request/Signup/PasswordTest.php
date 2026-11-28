<?php

namespace Tests\Feature\v1\Auth\Request\Signup;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Hash;
use Override;
use Tests\TestCase;

class PasswordTest extends TestCase
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

    // Laravel will NOT trim the spaces
    public function test_password_input_is_valid_although_the_value_has_spaces_at_the_beginning(): void
    {
        $passwordWithSpacesAtTheBeginning = '   ' . $this->password;

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $passwordWithSpacesAtTheBeginning,
                'password_confirmation' => $passwordWithSpacesAtTheBeginning
            ])
            ->assertNoContent();

        $user = User::firstWhere('email', $this->email);

        // Check with Hash::check
        $this->assertTrue(Hash::check($passwordWithSpacesAtTheBeginning, $user->password));
    }

    // Laravel will NOT trim the spaces
    public function test_password_input_is_valid_although_the_value_has_spaces_at_the_end(): void
    {
        $passwordWithSpacesAtTheEnd = $this->password . '   ';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $passwordWithSpacesAtTheEnd,
                'password_confirmation' => $passwordWithSpacesAtTheEnd
            ])
            ->assertNoContent();

        $user = User::firstWhere('email', $this->email);

        $this->assertNotNull($user);

        // Check with Hash::check
        $this->assertTrue(Hash::check($passwordWithSpacesAtTheEnd, $user->password));
    }

    // Laravel will NOT trim the spaces
    public function test_password_input_is_valid_although_the_value_has_spaces_at_the_begininng_and_the_end(): void
    {
        $passwordWithSpaces = '  ' . $this->password . '   ';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $passwordWithSpaces,
                'password_confirmation' => $passwordWithSpaces
            ])
            ->assertNoContent();

        $user = User::firstWhere('email', $this->email);

        $this->assertNotNull($user);

        // Check with Hash::check
        $this->assertTrue(Hash::check($passwordWithSpaces, $user->password));
    }

    public function test_password_input_is_valid_although_the_value_reached_max_character_length(): void
    {
        $password = '';
        $characterLength = 255;

        for ($index = 0; $index < $characterLength; $index++) {
            $password .= 'a';
        }

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $password,
                'password_confirmation' => $password
            ])
            ->assertNoContent();
    }

    public function test_password_input_is_valid_if_the_value_has_valid_password(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password
            ])
            ->assertNoContent();
    }

    public function test_password_input_throws_422_error_if_main_password_value_is_an_empty_string(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => '',
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_no_key_for_main_password_input(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_no_key_for_password_confirmation_input(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_password_confirmation_value_is_an_empty_string(): void
    {
        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => ''
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_the_value_doesnt_match_with_password_confirmation(): void
    {
        $differentPassword = $this->password;
        $differentPassword .= '1';

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $differentPassword
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_the_value_has_character_length_less_than_8(): void
    {
        $shortPassword = substr($this->password, 0, 7);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $shortPassword,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_the_value_has_character_length_more_than_255(): void
    {
        $password = '';
        $characterLength = 256;

        for ($index = 0; $index < $characterLength; $index++) {
            $password .= 'a';
        }

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_the_value_is_an_array(): void
    {
        $password = ['abc', 'def'];

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $password,
                'password_confirmation' => $this->password
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }

    public function test_password_input_throws_422_error_if_the_value_is_a_file(): void
    {
        // GD extension required for creating fake image
        $fakeImage = UploadedFile::fake()->image('test_image.jpg')->size(1024);

        $this->postJson(route('api.v1.auth.signup'), [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $fakeImage,
                'password_confirmation' => $fakeImage
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('password');
    }
}
