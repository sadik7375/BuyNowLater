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
        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->boolean('show_deposit')->default(true)->after('deposit_percentage');
            $table->boolean('show_reminders')->default(true)->after('show_deposit');
            $table->boolean('show_alerts')->default(true)->after('show_reminders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->dropColumn(['show_deposit', 'show_reminders', 'show_alerts']);
        });
    }
};
