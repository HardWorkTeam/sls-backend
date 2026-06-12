<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->string('category')->default('custom');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->string('location')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index(['wedding_id', 'category']);
            $table->index(['wedding_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_events');
    }
};
