<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->string('app_key', 64)->unique();
            $table->string('name', 128);
            $table->string('url', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('currency_unit', 20)->nullable();
            $table->string('date_update_time', 5);
            $table->boolean('sync_update_time')->default(false);
            $table->boolean('pity_system')->default(false);
            $table->integer('guarantee_count')->default(0);
            $table->timestampsTz(0);
        });
        // definition[], drop[] 等のカラムは生SQLで追加
        DB::statement('ALTER TABLE apps ADD COLUMN rarity_defs definition[];');
        DB::statement('ALTER TABLE apps ADD COLUMN marker_defs definition[];');
        DB::statement('ALTER TABLE apps ADD COLUMN task_defs JSONB;');
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
