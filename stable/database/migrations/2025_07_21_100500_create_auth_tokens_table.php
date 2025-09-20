<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('token', 255)->unique();
            $table->string('type');
            $table->string('code', 6)->nullable();
            $table->boolean('is_used')->default(false);
            $table->string('ip', 128)->nullable();
            $table->string('ua', 255)->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz(0);
        });

        // token_type ENUM ‚Ö‚Ì•ÏŠ·‚Í PostgreSQL ‚Ì‚Ý
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE auth_tokens ALTER COLUMN type TYPE token_type USING type::token_type;");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_tokens');
    }
};