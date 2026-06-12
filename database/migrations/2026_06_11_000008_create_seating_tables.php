<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wedding_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->string('table_name');
            $table->unsignedInteger('table_number')->nullable();
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->json('layout')->nullable();
            $table->timestamps();

            $table->unique(['wedding_id', 'table_name']);
            $table->unique(['wedding_id', 'table_number']);
        });

        Schema::create('guest_seatings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('wedding_table_id')->constrained('wedding_tables')->cascadeOnDelete();
            $table->unsignedSmallInteger('seat_number')->nullable();
            $table->timestamps();

            $table->unique(['wedding_id', 'guest_id']);
            $table->unique(['wedding_table_id', 'seat_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_seatings');
        Schema::dropIfExists('wedding_tables');
    }
};
