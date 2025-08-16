<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary(); // 'USD','JPY'
            $table->string('name'); // 'US Dollar'
            $table->string('symbol', 8)->nullable(); // '$','¥'
            $table->string('symbol_native', 8)->nullable();
            $table->unsignedTinyInteger('minor_unit'); // 0..6 程度
            $table->decimal('rounding', 8, 4)->default(0); // CHF=0.05など。0=四捨五入なし
            $table->string('name_plural')->nullable();
            $table->timestampsTz(0);
        });
    }

    public function down(): void {
        Schema::dropIfExists('currencies');
    }
};
