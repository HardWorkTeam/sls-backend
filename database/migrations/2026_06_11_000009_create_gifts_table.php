<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->string('gift_type');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('item_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['wedding_id', 'gift_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gifts');
    }
};
