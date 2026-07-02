<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('email');
            $table->string('product_id');
            $table->string('product_title');
            $table->string('product_handle');
            $table->string('product_image')->nullable();
            $table->decimal('product_price', 10, 2);
            $table->decimal('deposit_amount', 10, 2);
            $table->decimal('remaining_balance', 10, 2);
            $table->string('draft_order_id')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('status')->default('pending'); // pending, paid, completed, expired
            $table->string('token')->unique();
            $table->timestamps();

            $table->foreign('shop_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
