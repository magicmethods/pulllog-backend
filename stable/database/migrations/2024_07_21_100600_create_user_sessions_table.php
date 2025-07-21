<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->string('csrf_token', 255)->primary();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('email', 255);
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
