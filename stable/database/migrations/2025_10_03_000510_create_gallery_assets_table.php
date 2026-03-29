<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('app_id')->nullable();
            $table->unsignedBigInteger('log_id')->nullable();
            $table->string('disk', 32)->default('local');
            $table->string('path', 1024);
            $table->string('thumb_path_small', 1024)->nullable();
            $table->string('thumb_path_large', 1024)->nullable();
            $table->string('mime', 255);
            $table->bigInteger('bytes');
            $table->bigInteger('bytes_thumb_small')->default(0);
            $table->bigInteger('bytes_thumb_large')->default(0);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('hash_sha256', 64);
            $table->string('title', 120)->nullable();
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility', 32)->default('private');
            $table->timestampsTz(0);
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['app_id', 'created_at']);
            $table->index(['log_id', 'created_at']);
            $table->unique(['user_id', 'hash_sha256']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('app_id')->references('id')->on('apps')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE gallery_assets ALTER COLUMN id SET DEFAULT gen_random_uuid();");
            DB::statement("ALTER TABLE gallery_assets ALTER COLUMN visibility DROP DEFAULT;");
            DB::statement("ALTER TABLE gallery_assets ALTER COLUMN visibility TYPE visibility USING (visibility::visibility);");
            DB::statement("ALTER TABLE gallery_assets ALTER COLUMN visibility SET DEFAULT 'private'::visibility;");
            DB::statement("ALTER TABLE gallery_assets ALTER COLUMN disk DROP DEFAULT;");
            DB::statement("ALTER TABLE gallery_assets ALTER COLUMN disk TYPE storage_disk USING (disk::storage_disk);");
            DB::statement("ALTER TABLE gallery_assets ALTER COLUMN disk SET DEFAULT 'local'::storage_disk;");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gallery_assets')) {
            Schema::table('gallery_assets', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropForeign(['app_id']);
            });
        }

        Schema::dropIfExists('gallery_assets');
    }
};
