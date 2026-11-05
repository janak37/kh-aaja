<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->date('board_date');

            $table->string('status', 20)->default('available');
            $table->unsignedInteger('price')->nullable();

            $table->boolean('is_special')->default(false);
            $table->string('special_note', 80)->nullable();

            $table->timestamps();

            $table->unique(['item_id', 'board_date']);
            $table->index(['board_date', 'is_special']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_statuses');
    }
};
