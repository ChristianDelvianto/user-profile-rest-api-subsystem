<?php

namespace Tests\Feature\v1\Notification\Main;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
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

        $this->user = User::factory()
                    ->has(UserStat::factory(), 'stats')
                    ->create();
    }

    /**
     * The goal: The oldest notification become the first in order
     */
    public function test_notification_pagination_orders(): void
    {
        $notificationsCount = 4;

        for ($index = 0; $index < $notificationsCount; $index++) {
            $this->travel(rand(3, 5))->minutes();

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
                // Ensures the latest notification uuid match with the first item from the API response
                $json->where('items.0.uuid', $latestNotification->uuid)
                    ->etc();
            });

        $this->travel(10)->seconds();

        $oldestNotification = $this->user->notifications()->oldest()->first();

        /**
         * Now we begin the update, the earliest user in $oldestNotification group become the newest
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

    public function test_notifications_will_return_response_contains_expected_fields(): void
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

    // @todo: Add more tests
}
