<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invitation_templates')
            ->where('slug', 'phanaroth-luxury-v1')
            ->update(['slug' => 'red-rose-luxury-v1']);
    }

    public function down(): void
    {
        DB::table('invitation_templates')
            ->where('slug', 'red-rose-luxury-v1')
            ->update(['slug' => 'phanaroth-luxury-v1']);
    }
};
