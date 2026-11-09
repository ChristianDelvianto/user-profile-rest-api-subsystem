<?php

namespace App\Console\Commands\Notification;

use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SyncGroupsCountCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:sync-groups-count-value';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync notifications groups_count value from cache to database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /**
         * Note: Since we are using Redis facade to get all the keys,
         * make sure the value of 'prefix' in config/cache.php, set to '',
         * and also 'prefix' value in config/database.php line 152, set also to '' (Then clear the app cache)
         * 
         * Otherwise you need to mimic the key pattern created by those default 'prefix' value,
         * When we don't use default 'prefix' value, the cache will the store the key as written.
         * Example:
         * Cache::put('test_1') -> 'test_1'
         * 
         * When you use the default 'prefix' value, the key will change.
         * Example:
         * Cache::put('test_1') -> 'laravel-database-test_1'
         * 
         * In this repo, I have set the 'prefix' value to '' to prevent confusions
         */
        $keyPattern = config('custom.cache.notification.pattern.groups_count'); // e.g. notification:*:groups_count, see config/custom/cache.php for more details
        $chunkSize = 100;
        $iterator = '0';

        $redis = Redis::connection('cache')->client();

        do {
            $result = $redis->scan($iterator, ['COUNT' => $chunkSize, 'MATCH' => $keyPattern]);

            if ($result === false) {
                break;
            }

            $iterator = $result[0]; // Extract next iterator position
            $keys = $result[1]; // Extract keys array

            if (empty($keys)) {
                break;
            }

            foreach ($keys as $key) {
                /**
                 * Get the cache value and delete the cache key.
                 * 
                 * Queue will reinitialize the cache if the cache is missing, so we can safely delete the cache key after getting the value
                 * See \App\Jobs\Notification\UpdateGroupsCountCacheJob for more details
                 */
                $groupsCountCache = Cache::pull($key, null);

                if (is_null($groupsCountCache)) {
                    continue; // Skip if cache value is null
                }

                $notificationUuid = str_replace(':groups_count', '', str_replace('notification:', '', $key));

                // Update the notification groups_count with the cache value
                DB::transaction(function () use ($notificationUuid, $groupsCountCache): void {
                    $notification = Notification::where('uuid', $notificationUuid)->lockForUpdate()->first();

                    if ($notification) {
                        $notification->update(['groups_count' => (int) $groupsCountCache]);
                    }
                }, attempts: config('custom.database.transaction.attempts'));
            }
        } while ($iterator !== '0');
    }
}
