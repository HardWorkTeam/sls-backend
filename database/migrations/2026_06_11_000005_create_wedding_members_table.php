<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wedding_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('member_role');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['wedding_id', 'user_id']);
            $table->index(['user_id', 'member_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wedding_members');
    }
};
