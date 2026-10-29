<?php

namespace Tests\Feature\v1\User\Main;

use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\Fluent\AssertableJson;
use Override;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();
    }

    public function test_user_profile_can_be_fetch_successfully(): void
    {
        $this->getJson(route('api.v1.user.profile', ['user' => $this->user->username]))
            ->assertOk();
    }

    public function test_user_profile_can_be_fetch_successfully_using_case_insensitive_username(): void
    {
        $differentUsername = strtoupper($this->user->username);

        $this->assertFalse($this->user->username === $differentUsername);

        $this->getJson(route('api.v1.user.profile', ['user' => $differentUsername]))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->has('profile', function (AssertableJson $json) {
                        $json->where('id', $this->user->id)
                            ->etc();
                    })
                    ->etc();
            });
    }

    public function test_user_profile_response_contains_expected_fields(): void
    {
        $this->getJson(route('api.v1.user.profile', ['user' => $this->user->username]))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->has('followers_count')->where('followers_count', 0)
                    ->has('following_count')->where('following_count', 0)
                    ->has('is_followed')->where('is_followed', false)
                    ->has('profile', function (AssertableJson $json) {
                        $json->has('id')
                            ->has('username')
                            ->has('name')
                            ->etc();
                    })
                    ->etc();
            });
    }

    public function test_user_profile_will_have_is_following_key_set_to_false_for_guest(): void
    {
        $this->getJson(route('api.v1.user.profile', ['user' => $this->user->username]))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->has('is_followed')->where('is_followed', false)
                    ->etc();
            });
    }

    public function test_user_profile_response_has_is_followed_set_to_true_if_user_followed(): void
    {
        $follower = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $this->user->followers()->attach($follower->id);

        $this->actingAs($follower, 'web')
            ->getJson(route('api.v1.user.profile', ['user' => $this->user->username]))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->has('is_followed')->where('is_followed', true)
                    ->etc();
            });
    }

    public function test_user_profile_route_exists(): void
    {
        $this->getJson(route('api.v1.user.profile', ['user' => $this->user->username]))
            ->assertOk();
    }

    public function test_user_profile_route_for_non_existed_user_will_throw_404_error(): void
    {
        $this->getJson(route('api.v1.user.profile', ['user' => 'no_user']))
            ->assertNotFound();
    }

    // @todo: Add more tests
}
