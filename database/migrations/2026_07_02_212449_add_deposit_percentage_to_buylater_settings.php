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
            $table->integer('deposit_percentage')->default(10)->after('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->dropColumn('deposit_percentage');
        });
    }
};
