<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add role_id as UUID foreign key
            $table->uuid('role_id')->nullable()->after('role');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');

            // Note: We keep the old 'role' column for backward compatibility during migration
            // It can be removed later once all users have been migrated to the new system
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
