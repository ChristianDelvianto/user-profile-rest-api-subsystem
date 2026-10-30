<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_stats', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->unsignedBigInteger('following_count')->default(0);
            $table->unsignedBigInteger('notifications_count')->default(0);
            $table->unsignedBigInteger('posts_count')->default(0);
            $table->unsignedBigInteger('last_read_notification_id')->nullable();
            $table->unsignedBigInteger('last_read_notification_activity_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->primary('user_id');
        });

        // Add the non-negative constraint
        DB::statement(
            'ALTER TABLE user_stats
            ADD CONSTRAINT non_negative_columns
            CHECK (
                followers_count >= 0
                AND following_count >= 0
                AND notifications_count >= 0
                AND posts_count >= 0
            )'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_stats');
    }
};
