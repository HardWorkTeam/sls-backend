<?php

use App\Models\Guest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            // Short, human-friendly check-in code (e.g. "K7P2QX"). Scanned via
            // the guest's QR OR typed by hand at the door — far easier to key in
            // than the 26-char opaque token. Unique within a wedding.
            $table->string('check_in_code', 8)->nullable()->after('check_in_token');
        });

        // Backfill a unique short code for every existing guest, scoped per
        // wedding so a code is only ever entered against the right event.
        $used = [];
        DB::table('guests')->whereNull('check_in_code')->orderBy('id')
            ->select('id', 'wedding_id')->each(function ($guest) use (&$used) {
                $weddingId = $guest->wedding_id;
                do {
                    $code = Guest::randomCheckInCode();
                } while (isset($used[$weddingId][$code]));
                $used[$weddingId][$code] = true;

                DB::table('guests')->where('id', $guest->id)->update([
                    'check_in_code' => $code,
                ]);
            });

        Schema::table('guests', function (Blueprint $table) {
            $table->unique(['wedding_id', 'check_in_code']);
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropUnique(['wedding_id', 'check_in_code']);
            $table->dropColumn('check_in_code');
        });
    }
};
