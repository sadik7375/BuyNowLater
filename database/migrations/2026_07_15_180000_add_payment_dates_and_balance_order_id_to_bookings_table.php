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
            $table->string('order_id')->nullable()->after('draft_order_id');
            $table->string('balance_order_id')->nullable()->after('order_id');
            $table->timestamp('deposit_paid_at')->nullable()->after('expires_at');
            $table->timestamp('completed_at')->nullable()->after('deposit_paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['order_id', 'balance_order_id', 'deposit_paid_at', 'completed_at']);
        });
    }
};
