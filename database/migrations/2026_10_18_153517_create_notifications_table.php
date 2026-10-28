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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id(); // Internal usage to 'notification_user' table
            $table->uuid(); // Public-facing identifier
            $table->string('type', 64); // Support multiple types; See App\Enums\NotificationType
            $table->unsignedBigInteger('groups_count')->default(1);
            $table->foreignId('user_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->timestamps();

            // Indexes
            $table->unique('uuid');
            $table->index(['user_id', 'type', 'created_at']); // For creating new grouped notification
        });

        // Add the non-negative constraint
        DB::statement(
            'ALTER TABLE notifications
            ADD CONSTRAINT notifications_groups_count_non_negative
            CHECK (groups_count >= 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
