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
        Schema::create('post_stats', function (Blueprint $table) {
            /**
             * To keep this repo demo simple,
             * I decided not to implement comments, shares, and views features.
             * 
             * I'm open to consultation ;)
             */
            $table->foreignId('post_id')->constrained('posts', 'id')->cascadeOnDelete();
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();

            // Indexes
            $table->primary('post_id');
        });

        // Add the non-negative constraint
        DB::statement(
            'ALTER TABLE post_stats
            ADD CONSTRAINT non_negative_columns
            CHECK (
                comments_count >= 0
                AND likes_count >= 0
                AND shares_count >= 0
                AND views_count >= 0
            )'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_stats');
    }
};
