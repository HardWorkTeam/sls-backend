<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            // Opaque per-guest token encoded in the guest's QR code. Scanned on
            // the wedding day to mark the guest as arrived (checked in).
            $table->string('check_in_token', 32)->nullable()->after('is_vip');
            $table->timestamp('checked_in_at')->nullable()->after('check_in_token');
        });

        // Backfill a unique token for every existing guest.
        DB::table('guests')->whereNull('check_in_token')->orderBy('id')->each(function ($guest) {
            DB::table('guests')->where('id', $guest->id)->update([
                'check_in_token' => (string) Str::ulid(),
            ]);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->unique('check_in_token');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropUnique(['check_in_token']);
            $table->dropColumn(['check_in_token', 'checked_in_at']);
        });
    }
};
