<?php

namespace Tests\Feature\v1\ComplexTest\User;

use App\Models\User;
use App\Models\UserStat;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\Batch;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Override;
use Tests\TestCase;

/**
 * These tests below are using real HTTP requests and real database records,
 * each tests will not delete previous test data records.
 * 
 * Before test, make sure:
 * 1. Run php artisan migrate:fresh
 * 2. Run php artisan optimize:clear & php artisan optimize
 * 3. Clear all keys in Redis
 * 4. Value for QUEUE_CONNECTION in phpunit.xml must using "redis" value
 * 5. Value for CACHE_STORE in phpunit.xml must using "redis" value
 * 6. Run the test
 */
class FollowTest extends TestCase
{
    use WithFaker;

    protected string $baseUrl;
    protected array $requestHeaders;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

        $this->baseUrl = config('app.url');
        $this->requestHeaders = [
            'Accept' => 'application/json',
            'Origin' => $this->baseUrl,
            'Referer' => "{$this->baseUrl}/"
        ];
    }

    /**
     * Real-life scenario: multiple users concurrently follow the same user
     */
    public function test_follow_from_multiple_users_will_store_followers_count_cache_accurately(): void
    {
        $usersCount = 1000; // Adjust this if we want to test more / less concurency
        $usersSessions = []; // Store sessions of authenticated users

        $user = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->create();

        $followers = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->count($usersCount)
                    ->create();

        foreach ($followers as $follower) {
            $cookieJar = new CookieJar();

            // Initialize Sanctum CSRF protection
            Http::withOptions(['cookies' => $cookieJar])
                ->withHeaders($this->requestHeaders)
                ->get("{$this->baseUrl}/sanctum/csrf-cookie")
                ->throw();

            $csrfCookie = $cookieJar->getCookieByName('XSRF-TOKEN');

            $this->assertNotNull($csrfCookie);

            // Log in using each follower's own session.
            $response = Http::withOptions(['cookies' => $cookieJar])
                        ->withHeaders([
                            ...$this->requestHeaders,
                            'X-XSRF-TOKEN' => urldecode($csrfCookie->getValue())
                        ])
                        ->post("{$this->baseUrl}/api/v1/auth/login", [
                            'email' => $follower->email,
                            'password' => 'password'
                        ])
                        ->throw();

            $this->assertEquals(200, $response->status(), 'Login failed for user id: ' . $follower->id);

            $csrfCookie = $cookieJar->getCookieByName('XSRF-TOKEN');

            $this->assertNotNull($csrfCookie);

            // Stores each $follower cookie and csrf_token for later use
            $usersSessions[$follower->id] = [
                'cookies' => $cookieJar,
                'csrf_token' => urldecode($csrfCookie->getValue()),
            ];
        }

        // Ensures all users are authenticated
        $this->assertEquals($usersCount, count($usersSessions), 'Failed to authenticate all users');

        // Perform real http requests
        Http::batch(function (Batch $batch) use ($followers, $user, $usersSessions) {
            $followers->map(function (User $follower) use ($batch, $user, $usersSessions) {
                $batch->as($follower->id)
                    ->withOptions([
                        'cookies' => $usersSessions[$follower->id]['cookies'],
                    ])
                    ->withHeaders([
                        ...$this->requestHeaders,
                        'X-XSRF-TOKEN' => $usersSessions[$follower->id]['csrf_token'],
                    ])
                    ->post("{$this->baseUrl}/api/v1/user/{$user->id}/follow");
            })->all();
        })
        ->send();

        $this->artisan('queue:work', [
                'connection' => 'redis',
                '--queue' => 'high,default',
                '--stop-when-empty' => true,
                '--sleep' => 0
            ])
            ->assertExitCode(0);

        // Ensure $user only have one notification (Because we are using queue pipeline)
        $this->assertEquals(1, $user->notifications()->count());

        // Ensures $user followers count equal with $usersCount
        $this->assertEquals($usersCount, $user->followers()->count());

        // Ensures followers_count cache value equal with $usersCount
        $this->assertEquals($usersCount, (int) Cache::get(str_replace('*', $user->id, config('custom.cache.user_stat.pattern.followers_count')), 0));

        // Ensures notifications_count cache value equal with $usersCount
        $this->assertEquals($usersCount, (int) Cache::get(str_replace('*', $user->id, config('custom.cache.user_stat.pattern.notifications_count')), 0));
    }

    /**
     * A user tries to follow another user concurrently
     */
    public function test_follow_must_ensure_follower_uniqueness(): void
    {
        $concurrency = 5;

        $user = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->create();

        $follower = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();

        $cookieJar = new CookieJar();

        // Initialize Sanctum CSRF protection
        Http::withOptions(['cookies' => $cookieJar])
            ->withHeaders($this->requestHeaders)
            ->get("{$this->baseUrl}/sanctum/csrf-cookie")
            ->throw();

        $csrfCookie = $cookieJar->getCookieByName('XSRF-TOKEN');

        $this->assertNotNull($csrfCookie);

        // Log in as $follower
        $response = Http::withOptions(['cookies' => $cookieJar])
                    ->withHeaders([
                        ...$this->requestHeaders,
                        'X-XSRF-TOKEN' => urldecode($csrfCookie->getValue())
                    ])
                    ->post("{$this->baseUrl}/api/v1/auth/login", [
                        'email' => $follower->email,
                        'password' => 'password'
                    ])
                    ->throw();

        $this->assertEquals(200, $response->status(), 'Login failed for user id: ' . $follower->id);

        $csrfCookie = $cookieJar->getCookieByName('XSRF-TOKEN');

        $this->assertNotNull($csrfCookie);

        // Perform real http requests
        Http::batch(function (Batch $batch) use ($concurrency, $cookieJar, $csrfCookie, $user) {
            for ($index = 0; $index < $concurrency; $index++) {
                $batch->as(($index + 1))
                    ->withOptions(['cookies' => $cookieJar])
                    ->withHeaders([
                        ...$this->requestHeaders,
                        'X-XSRF-TOKEN' => urldecode($csrfCookie->getValue())
                    ])
                    ->post("{$this->baseUrl}/api/v1/user/{$user->id}/follow");
            }
        })
        ->send();

        // Ensures only 1 follower, no duplicate records
        $this->assertEquals(1, $user->followers()->count());
    }
}
