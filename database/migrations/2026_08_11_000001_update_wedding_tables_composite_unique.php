<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the two separate unique indexes on wedding_tables
 * (wedding_id, table_name) and (wedding_id, table_number)
 * with a single composite unique index on
 * (wedding_id, table_name, table_number).
 *
 * This means a duplicate is only detected when BOTH the table name
 * AND the table number are identical for the same wedding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wedding_tables', function (Blueprint $table) {
            // Drop the two separate unique indexes.
            $table->dropUnique(['wedding_id', 'table_name']);
            $table->dropUnique(['wedding_id', 'table_number']);

            // Add one composite unique index (NULL-safe via DB engine).
            $table->unique(['wedding_id', 'table_name', 'table_number'], 'wedding_tables_name_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wedding_tables', function (Blueprint $table) {
            $table->dropUnique('wedding_tables_name_number_unique');

            // Restore the original separate indexes.
            $table->unique(['wedding_id', 'table_name']);
            $table->unique(['wedding_id', 'table_number']);
        });
    }
};
