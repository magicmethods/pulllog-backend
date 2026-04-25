<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS logs_with_money');

            DB::statement(<<<'SQL'
CREATE VIEW logs_with_money AS
SELECT
    l.*, 
    a.currency_code,
    c.minor_unit,
    (
        CAST(l.expense_amount AS REAL) /
        CASE c.minor_unit
            WHEN 0 THEN 1
            WHEN 1 THEN 10
            WHEN 2 THEN 100
            WHEN 3 THEN 1000
            WHEN 4 THEN 10000
            WHEN 5 THEN 100000
            WHEN 6 THEN 1000000
            ELSE 1
        END
    ) AS expense_decimal
FROM logs l
JOIN apps a ON a.id = l.app_id
JOIN currencies c ON c.code = a.currency_code;
SQL);

            return;
        }

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
