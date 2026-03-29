<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_asset_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('asset_id');
            $table->string('code', 32)->unique();
            $table->timestampTz('expire_at')->nullable();
            $table->timestampTz('last_accessed_at')->nullable();
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestampsTz(0);

            $table->unique('asset_id');
            $table->foreign('asset_id')->references('id')->on('gallery_assets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('gallery_asset_links')) {
            Schema::table('gallery_asset_links', function (Blueprint $table) {
                $table->dropForeign(['asset_id']);
            });
        }

        Schema::dropIfExists('gallery_asset_links');
    }
};
