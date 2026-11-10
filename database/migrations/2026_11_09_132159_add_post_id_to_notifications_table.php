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
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('post_id')->after('data')->nullable()->constrained('posts', 'id')->cascadeOnDelete();

            // Indexes
            $table->index(['post_id', 'type', 'created_at'], 'notifications_post_id_type_created_at_index'); // For creating new grouped notification
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_post_id_type_created_at_index');

            $table->dropColumn('post_id');
        });
    }
};
