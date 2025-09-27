<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context', 50);
            $table->string('version', 20);
            $table->json('layout');
            $table->json('filters')->default('{}');
            $table->timestampsTz(0);

            $table->unique(['user_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_filters');
    }
};