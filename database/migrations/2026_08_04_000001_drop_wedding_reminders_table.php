<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove the retired wedding countdown reminder delivery log from existing
     * deployments. Fresh installations never create the table.
     */
    public function up(): void
    {
        Schema::dropIfExists('wedding_reminders');
    }

    public function down(): void
    {
        // The retired feature has no table to restore.
    }
};
