<?php

namespace Tests\Feature\v1\Auth\Main;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_login_route_exists(): void
    {
        $this->postJson(route('api.v1.auth.login'))
            ->assertOk();
    }
}
