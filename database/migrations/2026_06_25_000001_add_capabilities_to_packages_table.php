<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Structured, admin-editable definition of what the package
            // unlocks: { modules: {seating, gallery, gifts},
            // guest_limit, invitation_design_limit }. Null limits = unlimited.
            // When null, capabilities are derived from the feature strings.
            $table->json('capabilities')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('capabilities');
        });
    }
};
