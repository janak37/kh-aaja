<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->date('board_date');

            // Personal data: emptied by the purge job 30 days after board_date
            $table->string('customer_name', 80)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamp('consent_at');
            $table->timestamp('purged_at')->nullable();

            $table->unsignedTinyInteger('quantity');
            $table->string('status', 20)->default('pending');
            $table->string('decline_reason', 20)->nullable();
            $table->timestamp('decided_at')->nullable();

            // Powers the customer's status link, no login needed
            $table->string('public_token', 64)->unique();

            $table->timestamps();

            $table->index(['shop_id', 'status', 'board_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
