<?php

namespace Tests\Feature\v1\Auth\Main;

use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\Fluent\AssertableJson;
use Override;
use Tests\TestCase;

class CheckTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => config('app.url')
        ]);

        $this->user = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();
    }

    public function test_check_response_will_return_expected_fields(): void
    {
        $this->actingAs($this->user, 'web')
            ->getJson(route('api.v1.auth.check'))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->has('is_verified')
                    ->has('profile')
                    ->etc();
            });
    }

    public function test_check_route_exists(): void
    {
        $this->getJson(route('api.v1.auth.check'))
            ->assertUnauthorized();
    }

    public function test_check_route_requires_authentication(): void
    {
        $this->getJson(route('api.v1.auth.check'))
            ->assertUnauthorized();
    }
}
