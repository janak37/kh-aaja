<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Profile
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('description', 500)->nullable();
            $table->string('category', 50)->index();
            $table->string('area', 80)->index();
            $table->string('phone', 20);
            $table->string('whatsapp_number', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('cover_photo_path')->nullable();

            // Hours and dates: "today" always comes from the shop's timezone
            $table->string('timezone', 50)->default('Asia/Kathmandu');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();

            // Admin
            $table->boolean('is_pro')->default(false);
            $table->timestamp('suspended_at')->nullable();

            // Ranking (filled in by the score job later)
            $table->unsignedInteger('favourites_count')->default(0);
            $table->unsignedTinyInteger('score')->default(0)->index();
            $table->json('score_parts')->nullable();
            $table->timestamp('score_updated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
