<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weddings', function (Blueprint $table) {
            $table->id();
            $table->string('wedding_code')->unique();
            $table->string('wedding_name');
            $table->string('bride_name');
            $table->string('groom_name');
            $table->string('bride_photo_path')->nullable();
            $table->string('groom_photo_path')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('wedding_date')->nullable();
            $table->time('wedding_time')->nullable();
            $table->string('ceremony_venue')->nullable();
            $table->string('reception_venue')->nullable();
            $table->string('google_map_link', 2048)->nullable();
            $table->text('story_description')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weddings');
    }
};
