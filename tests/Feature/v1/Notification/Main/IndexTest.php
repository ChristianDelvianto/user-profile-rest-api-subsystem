<?php

namespace Tests\Feature\v1\Notification\Main;

use App\Enums\NotificationType;
use App\Jobs\Notification\UpdateGroupsCountCacheJob;
use App\Jobs\Notification\User\CreateAccountFollowerTypeOrAttachNewUserJob;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Override;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);

        $this->user = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();
    }

    public function test_can_fetch_notifications_successfully(): void
    {
        $this->actingAs($this->user, 'web')
            ->getJson(route('api.v1.notifications.index'))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->has('items', 0)
                    ->etc();
            });
    }

    /**
     * The goal: The oldest notification become the first in order.
     */
    public function test_notification_pagination_orders(): void
    {
        $notificationsCount = rand(7, 18);

        for ($index = 0; $index < $notificationsCount; $index++) {
            // Gives time gap between each notification
            $this->travel(rand(3, 7))->minutes();

            Notification::factory()
                        // Each notification has 3 users
                        ->has(
                            User::factory()
                                ->has(UserStat::factory(), 'stats')
                                ->count(3)
                        , 'users')
                        ->create([
                            'uuid' => Str::uuid()->toString(),
                            'type' => NotificationType::AccountFollower,
                            'user_id' => $this->user->id
                        ]);
        }

        $latestNotification = $this->user->notifications()->latest()->first();

        $this->actingAs($this->user, 'web')
            ->getJson(route('api.v1.notifications.index'))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) use ($latestNotification) {
                // Ensures the latest notification's uuid match with the first item from the API response
                $json->where('items.0.uuid', $latestNotification->uuid)
                    ->etc();
            });

        // Fast forward 10 seconds to the future
        $this->travel(10)->seconds();

        $oldestNotification = $this->user->notifications()->oldest()->first();

        /**
         * Now we begin the update, the earliest user in $oldestNotification users group become the newest
         * (We order the notifications based from column 'created_at' from notification_user table)
         */
        DB::table('notification_user')
        ->where('notification_id', $oldestNotification->id)
        ->oldest('user_id')
        ->update(['created_at' => now()]);

        $this->actingAs($this->user, 'web')
            ->getJson(route('api.v1.notifications.index'))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) use ($latestNotification, $oldestNotification) {
                // Ensures the oldest notification (before update), become the first item in order
                $json->where('items.0.uuid', $oldestNotification->uuid)
                    // Ensures the latest notification (before update), become the second item in order
                    ->where('items.1.uuid', $latestNotification->uuid)
                    ->etc();
            });
    }

    public function test_notification_will_return_response_contains_expected_fields(): void
    {
        // Create a notification to check its json keys
        Notification::factory()
                    ->has(
                        User::factory()
                            ->has(UserStat::factory(), 'stats')
                    , 'users')
                    ->create([
                        'uuid' => Str::uuid()->toString(),
                        'type' => NotificationType::AccountFollower,
                        'groups_count' => 1,
                        'user_id' => $this->user->id
                    ]);

        $this->actingAs($this->user, 'web')
            ->getJson(route('api.v1.notifications.index'))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->has('has_more')
                    ->has('cursor')
                    ->has('items', 1)
                        // See NotificationResource for more details
                        ->has('items.0', function (AssertableJson $json) {
                            $json->has('uuid')
                                ->has('type')
                                ->has('groups_count')
                                ->has('created_at')
                                ->has('last_activity_id')
                                ->has('last_activity_at')
                                ->has('users', 1)
                                ->etc();
                        })
                    ->etc();
            });
    }

    public function test_notification_response_will_reflect_precise_groups_count(): void
    {
        $usersCount = 6;

        $users = User::factory()
                ->has(UserStat::factory(), 'stats')
                ->count($usersCount)
                ->create();

        foreach ($users as $index => $user) {
            $this->actingAs($user, 'web')
                ->postJson(route('api.v1.user.follow', ['user' => $this->user->id]))
                ->assertNoContent();

            Queue::pushed(CreateAccountFollowerTypeOrAttachNewUserJob::class)->last()->handle();

            $job = Queue::pushed(UpdateGroupsCountCacheJob::class)->last();

            if ($index === 0) {
                $this->assertNull($job); // First user of notification
            } else {
                $this->assertNotNull($job);

                $job->handle();
            }
        }

        $this->assertDatabaseCount('notification_user', $usersCount)
            ->assertDatabaseCount(Notification::class, 1)
            ->assertDatabaseHas(Notification::class, [
                'type' => NotificationType::AccountFollower->value,
                'groups_count' => 1,
                'user_id' => $this->user->id
            ]);

        $this->actingAs($this->user, 'web')
            ->getJson(route('api.v1.notifications.index'))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) use ($usersCount) {
                $json->has('items', 1)
                    ->has('items.0', function (AssertableJson $json) use ($usersCount) {
                        $json->has('groups_count')->where('groups_count', $usersCount)
                            // Max 5 users
                            ->has('users', $usersCount <= 5 ? $usersCount : 5)
                            ->etc();
                    })
                    ->etc();
            });
    }

    public function test_notification_route_exist(): void
    {
        $this->getJson(route('api.v1.notifications.index'))
            ->assertUnauthorized();
    }

    public function test_notification_requires_authentication(): void
    {
        $this->getJson(route('api.v1.notifications.index'))
            ->assertUnauthorized();
    }

    // @todo: Add more tests
}
