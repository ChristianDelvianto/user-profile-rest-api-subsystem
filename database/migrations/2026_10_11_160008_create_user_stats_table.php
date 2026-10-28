<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->timestamps();

            // Indexes
            $table->primary('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_stats');
    }
};
