<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->text('description')->nullable();
            $table->integer('max_apps')->default(5);
            $table->integer('max_app_name_length')->default(30);
            $table->integer('max_app_desc_length')->default(400);
            $table->integer('max_log_tags')->default(5);
            $table->integer('max_log_tag_length')->default(22);
            $table->integer('max_log_text_length')->default(250);
            $table->integer('max_logs_per_app')->default(-1);
            $table->integer('max_storage_mb')->default(100);
            $table->integer('price_per_month')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
