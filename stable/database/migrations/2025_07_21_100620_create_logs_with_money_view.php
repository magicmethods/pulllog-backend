<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW logs_with_money AS
SELECT
    l.*,
    a.currency_code,
    c.minor_unit,
    (l.expense_amount::numeric / (10::numeric ^ c.minor_unit)) AS expense_decimal
FROM logs l
JOIN apps a ON a.id = l.app_id
JOIN currencies c ON c.code = a.currency_code;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS logs_with_money');
    }
};
