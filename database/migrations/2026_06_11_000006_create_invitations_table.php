<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('invitation_template_id')->nullable()->constrained('invitation_templates')->nullOnDelete();
            $table->string('invitation_code')->unique();
            $table->string('title')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->json('settings')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['wedding_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
