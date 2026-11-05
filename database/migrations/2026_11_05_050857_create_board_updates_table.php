<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->date('board_date');
            $table->timestamp('last_updated_at');

            $table->unique(['shop_id', 'board_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_updates');
    }
};
