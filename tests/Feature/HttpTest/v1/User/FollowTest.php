<?php

namespace Tests\Feature\HttpTest\v1\User;

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
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * These tests below are using real HTTP requests and real database records.
 * (EACH TESTS WILL NOT DELETE PREVIOUS TEST RECORDS; IN SHORT, THIS TESTS USE PERSISTENT DATABASE AND CACHE)
 * So we have to create a new user for each tests (like in normal feature test: $this->user).
 * 
 * Before test, make sure:
 * 1. Run php artisan migrate:fresh
 * 2. Run php artisan optimize:clear & php artisan optimize
 * 3. Clear all keys in Redis
 * 4. Value for QUEUE_CONNECTION in phpunit.xml must use "redis"
 * 5. Value for CACHE_STORE in phpunit.xml must use "redis"
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
     * A user tries to follow another user concurrently
     */
    public function test_follow_must_ensure_follower_uniqueness(): void
    {
        $concurrencyCount = 5;

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

        // This line is mandatory, otherwise the test will throw 419 error
        $csrfCookie = $cookieJar->getCookieByName('XSRF-TOKEN');

        $this->assertNotNull($csrfCookie);

        // Perform real http requests
        Http::batch(function (Batch $batch) use ($concurrencyCount, $cookieJar, $csrfCookie, $user) {
            for ($index = 0; $index < $concurrencyCount; $index++) {
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
        $this->assertSame(1, $user->followers()->count());
    }

    /**
     * Real-life scenario: multiple users concurrently follow the same user.
     * 
     * This test will send 10000 concurent http requests to a endpoint.
     */
    public function test_follow_from_10000_concurrent_users(): void
    {
        $usersCount = 10000; // Adjust this if we want to test more / less concurency
        $usersSessions = []; // Store sessions of authenticated users

        $user = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->create();

        $followers = collect([]);

        for ($index = 0; $index < $usersCount; $index++ ) {
            $username = 'test_' . ($index + 1);

            $follower = User::factory()
                        ->has(UserStat::factory(), 'stats')
                        ->create([ // To prevent unique constraint exception
                            'username' => $username,
                            'email' => $username . '@example.com'
                        ]);

            $followers->push($follower);

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
                        'cookies' => $usersSessions[$follower->id]['cookies']
                    ])
                    ->withHeaders([
                        ...$this->requestHeaders,
                        'X-XSRF-TOKEN' => $usersSessions[$follower->id]['csrf_token']
                    ])
                    ->post("{$this->baseUrl}/api/v1/user/{$user->id}/follow");
            })->all();
        })
        ->send(); // FIRE!!!

        $worker = new Process([
                    PHP_BINARY,
                    base_path('artisan'),
                    'queue:work',
                    '--queue=high,default',
                    '--stop-when-empty',
                    '--sleep=0',
                    '--memory=1024',
                    '--quiet',
                    '-v'
                ], base_path());

        $worker->setTimeout(null);
        $worker->run();

        // Ensures $worker end successfully
        $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());

        // Ensures $user followers count equal with $usersCount
        $this->assertSame($usersCount, $user->followers()->count());

        // Ensures $user followers_count cache value equal with $usersCount
        $this->assertSame($usersCount, (int) Cache::get(str_replace('*', $user->id, config('custom.cache.user_stat.pattern.followers_count')), 0));

        // Ensures $user notifications_count cache value equal with $usersCount
        $this->assertSame($usersCount, (int) Cache::get(str_replace('*', $user->id, config('custom.cache.user_stat.pattern.notifications_count')), 0));

        // Ensure $user only have one notification (Because we are using queue pipeline)
        $this->assertSame(1, $user->notifications()->count());
    }
}
