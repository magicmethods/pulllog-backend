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
            //$table->string('currency_unit', 20)->nullable();
            $table->char('currency_code', 3); // 通貨はISO 4217の3桁コードで管理（currencies.code 参照）

            $table->string('date_update_time', 5);
            $table->boolean('sync_update_time')->default(false);
            $table->boolean('pity_system')->default(false);
            $table->integer('guarantee_count')->default(0);

            // JSONBカラムを使用して定義を保存
            $table->jsonb('rarity_defs')->nullable();
            $table->jsonb('marker_defs')->nullable();
            $table->jsonb('task_defs')->nullable();

            $table->timestampsTz(0);

            // 外部キー（通貨マスタ）
            $table->foreign('currency_code')
                  ->references('code')->on('currencies')
                  ->cascadeOnUpdate();
        });
        // PostgreSQLの配列型（definition[]）カラムをLaravel（Eloquent）で取り扱うのが難しいためJSONB型に変更
        // - もし将来的に配列型を使用する場合は、以下のようにカラムを追加する
        // DB::statement('ALTER TABLE apps ADD COLUMN rarity_defs TYPE definition[];');
        // DB::statement('ALTER TABLE apps ADD COLUMN marker_defs TYPE definition[];');
        // DB::statement('ALTER TABLE apps ADD COLUMN task_defs TYPE definition[];');
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
