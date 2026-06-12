<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('custom');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['wedding_id', 'type']);
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('guest_group_id')->nullable()->constrained('guest_groups')->nullOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained('invitations')->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['wedding_id', 'name']);
            $table->index(['wedding_id', 'phone']);
        });

        Schema::create('rsvp_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('invitation_id')->constrained('invitations')->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->string('guest_name');
            $table->string('phone')->nullable();
            $table->unsignedSmallInteger('number_of_guests')->default(1);
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['wedding_id', 'status']);
            $table->index(['invitation_id', 'responded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsvp_responses');
        Schema::dropIfExists('guests');
        Schema::dropIfExists('guest_groups');
    }
};
