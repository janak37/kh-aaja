<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('dish_key', 100)->index();
            $table->string('description', 140)->nullable();
            $table->unsignedInteger('default_price')->nullable();
            $table->boolean('is_veg')->default(false);
            $table->string('photo_path')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->unsignedInteger('favourites_count')->default(0);

            $table->timestamps();

            $table->index(['shop_id', 'is_archived', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
