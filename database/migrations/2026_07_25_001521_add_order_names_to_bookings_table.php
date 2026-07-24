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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('order_name')->nullable();
            $table->string('balance_order_name')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('fulfillment_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['order_name', 'balance_order_name', 'payment_status', 'fulfillment_status']);
        });
    }
};

