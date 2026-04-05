<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->date('log_date');
            $table->integer('total_pulls');
            $table->integer('discharge_items');
            $table->bigInteger('expense_amount')->default(0);
            $table->jsonb('drop_details')->nullable();
            $table->jsonb('tags')->nullable();
            $table->text('free_text')->nullable();
            $table->jsonb('images')->nullable();
            $table->jsonb('tasks')->nullable();
            $table->timestampsTz(0);

            $table->unique(['user_id', 'app_id', 'log_date']);
            $table->index(['user_id', 'app_id', 'log_date']);
            $table->index(['user_id', 'log_date']);
            $table->index(['user_id', 'expense_amount']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE logs ADD CONSTRAINT logs_expense_non_negative CHECK (expense_amount >= 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
