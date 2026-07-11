<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->string('product_targeting_type')->default('all');
            $table->text('targeted_product_ids')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->dropColumn(['product_targeting_type', 'targeted_product_ids']);
        });
    }
};
