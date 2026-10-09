<?php

namespace Tests\Feature\v1\User\Main;

use App\Models\User;
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

        $this->user = User::factory()->create();
    }

    public function test_user_can_be_fetch_successfully(): void
    {
        $this->getJson(route('api.v1.user.profile', ['user' => $this->user->username]))
            ->assertOk();
    }

    public function test_user_can_be_fetch_successfully_using_case_insensitive_username(): void
    {
        $username = strtoupper($this->user->username);

        $this->assertTrue($this->user->username !== $username);

        $this->getJson(route('api.v1.user.profile', ['user' => $username]))
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
            ->assertJsonStructure([
                'profile'
            ]);
    }
}
