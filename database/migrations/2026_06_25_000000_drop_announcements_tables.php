<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// The announcements / notification-logs feature was removed. This migration
// drops the now-unused tables (no-op on fresh databases where they were never
// created, since the original create migration has been deleted).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('announcements');
    }

    public function down(): void
    {
        // Feature removed — intentionally not recreated.
    }
};
