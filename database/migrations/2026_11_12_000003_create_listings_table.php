<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('url', 2048);
            $table->boolean('is_own')->default(false);
            $table->string('status')->default('active');
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            // Money is stored in integer minor units (cents), never as float.
            $table->unsignedInteger('current_price')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->timestamps();

            // One listing per product per store.
            $table->unique(['product_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
