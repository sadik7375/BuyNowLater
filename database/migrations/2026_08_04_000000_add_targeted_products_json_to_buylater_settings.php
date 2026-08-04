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
            if (!Schema::hasColumn('buylater_settings', 'targeted_products_json')) {
                $table->text('targeted_products_json')->nullable()->after('targeted_product_ids');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buylater_settings', function (Blueprint $table) {
            if (Schema::hasColumn('buylater_settings', 'targeted_products_json')) {
                $table->dropColumn('targeted_products_json');
            }
        });
    }
};
