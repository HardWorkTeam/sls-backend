<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['wedding_id', 'name']);
        });

        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('album_id')->nullable()->constrained('albums')->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('media_type');
            $table->string('storage_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['wedding_id', 'media_type']);
            $table->index(['album_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_items');
        Schema::dropIfExists('albums');
    }
};
