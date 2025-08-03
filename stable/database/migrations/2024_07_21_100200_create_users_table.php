<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('name', 64);
            $table->string('avatar_url', 255)->nullable();
            $table->json('roles');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('restrict');
            $table->timestampTz('plan_expiration');
            $table->string('language', 10);
            $table->string('theme')->default('light'); // ENUM: theme
            $table->string('home_page', 20)->default('/apps');
            $table->timestampTz('last_login')->nullable();
            $table->string('last_login_ip', 128)->nullable();
            $table->string('last_login_ua', 255)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('remember_token', 255)->nullable();
            $table->json('unread_notices')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampsTz(0);
        });

        // カラム型変更: theme ENUM（デフォルト値を一時的に外してから型変換）
        DB::statement("ALTER TABLE users ALTER COLUMN theme DROP DEFAULT;");
        DB::statement('ALTER TABLE users ALTER COLUMN theme TYPE theme USING theme::theme');
        DB::statement("ALTER TABLE users ALTER COLUMN theme SET DEFAULT 'light';");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
