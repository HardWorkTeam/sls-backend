<?php

DB::statement('ROLLBACK;');
DB::statement("INSERT INTO migrations (migration, batch) VALUES ('2026_07_13_000001_add_currency_to_gifts_and_expenses_tables', 10) ON CONFLICT DO NOTHING;");
