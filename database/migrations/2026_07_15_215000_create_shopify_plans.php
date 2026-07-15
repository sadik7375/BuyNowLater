<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert Premium Plan into plans table
        DB::table('plans')->insertOrIgnore([
            'id' => 1,
            'type' => 'RECURRING',
            'name' => 'Premium Plan',
            'price' => 5.00,
            'interval' => 'EVERY_30_DAYS',
            'capped_amount' => 5.00,
            'terms' => 'Unlimited reservations, reminders, and alerts.',
            'test' => true, // Set to true for sandbox testing
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('plans')->where('id', 1)->delete();
    }
};
