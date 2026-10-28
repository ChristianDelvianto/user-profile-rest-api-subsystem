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
            ->assertJson(function (AssertableJson $json) use ($oldestNotification) {
                // Ensures the oldest notification (before update), become the first one in order
                $json->where('items.0.uuid', $oldestNotification->uuid)
                    ->etc();
            });
    }

    public function test_notifications_will_return_response_contains_expected_fields(): void
    {
        $this->actingAs($this->user, 'web')
            ->getJson(route('api.v1.notifications.index'))
            ->assertOk()
            ->assertJson(function (AssertableJson $json) {
                $json->has('has_more')->where('has_more', false)
                    ->has('cursor')->where('cursor', null)
                    ->has('items', 0)
                    ->etc();
            });
    }

    // @todo: Add more tests
}
