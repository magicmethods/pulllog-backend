<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('max_gallery_mb')->default(300);
            $table->integer('max_upload_mb_per_file')->default(20);
            $table->boolean('external_storage_allowed')->default(false);
            $table->boolean('transcode_webp')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'max_gallery_mb',
                'max_upload_mb_per_file',
                'external_storage_allowed',
                'transcode_webp',
            ]);
        });
    }
};
