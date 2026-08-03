<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wedding_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->string('milestone'); // month_before, week_before, wedding_day

            // The date the reminder was sent *for*, not the date it was sent
            // on. Part of the unique key so moving the wedding to a new date
            // re-arms every milestone: the couple gets a fresh countdown
            // instead of silence because the old date's rows already exist.
            $table->date('wedding_date');

            $table->json('recipients')->nullable(); // addresses actually mailed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // The idempotency guard. The send is claimed by inserting this row
            // first, so two overlapping cron runs cannot both mail the couple:
            // the loser hits this constraint and skips.
            $table->unique(['wedding_id', 'milestone', 'wedding_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wedding_reminders');
    }
};
