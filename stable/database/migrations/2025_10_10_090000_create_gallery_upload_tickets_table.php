<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_upload_tickets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('token', 128)->unique();
            $table->unsignedBigInteger('app_id')->nullable();
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('expected_bytes')->nullable();
            $table->string('mime', 255)->nullable();
            $table->unsignedBigInteger('max_bytes');
            $table->string('visibility', 32)->default('private');
            $table->unsignedBigInteger('log_id')->nullable();
            $table->json('tags')->nullable();
            $table->json('meta')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampsTz(0);

            $table->index(['user_id', 'app_id', 'expires_at']);
            $table->index(['expires_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('app_id')->references('id')->on('apps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('gallery_upload_tickets')) {
            Schema::table('gallery_upload_tickets', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropForeign(['app_id']);
            });
        }

        Schema::dropIfExists('gallery_upload_tickets');
    }
};
