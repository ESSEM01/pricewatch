<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forecasts are computed by a queued job and cached here, so product
     * pages never run the models on a request.
     */
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->unsignedTinyInteger('horizon_days');
            // Mean absolute percentage error from the walk-forward backtest.
            $table->decimal('mape', 6, 3)->nullable();
            // [{date, price, lower, upper}] in cents.
            $table->json('points');
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecasts');
    }
};
