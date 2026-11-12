<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only price changes plus one daily heartbeat are stored, so the table
     * stays small and charts can draw it as a step line.
     */
    public function up(): void
    {
        Schema::create('price_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->char('currency', 3);
            $table->timestamp('recorded_at');

            $table->index(['listing_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_points');
    }
};
