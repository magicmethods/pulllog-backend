<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_usage_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->bigInteger('bytes_used')->default(0);
            $table->bigInteger('files_count')->default(0);
            $table->timestampsTz(0);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gallery_usage_stats', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::dropIfExists('gallery_usage_stats');
    }
};
