<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats_cache', function (Blueprint $table) {
            $table->integer('user_id')->nullable();
            $table->string('cache_key', 128)->primary();
            $table->json('value');
            $table->timestampsTz(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_cache');
    }
};
