<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gallery_assets')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS gallery_assets_user_created_active_idx ON gallery_assets (user_id, created_at DESC) WHERE deleted_at IS NULL'
            );

            return;
        }

        Schema::table('gallery_assets', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'deleted_at', 'created_at'],
                'gallery_assets_user_deleted_created_idx'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('gallery_assets')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS gallery_assets_user_created_active_idx');

            return;
        }

        Schema::table('gallery_assets', function (Blueprint $table): void {
            $table->dropIndex('gallery_assets_user_deleted_created_idx');
        });
    }
};
