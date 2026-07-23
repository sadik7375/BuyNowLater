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
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (!Schema::hasColumn('bookings', 'selling_plan_id')) {
                    $table->string('selling_plan_id')->nullable()->after('draft_order_id');
                }
                if (!Schema::hasColumn('bookings', 'selling_plan_group_id')) {
                    $table->string('selling_plan_group_id')->nullable()->after('selling_plan_id');
                }
                if (!Schema::hasColumn('bookings', 'subscription_contract_id')) {
                    $table->string('subscription_contract_id')->nullable()->after('selling_plan_group_id');
                }
                if (!Schema::hasColumn('bookings', 'payment_type')) {
                    $table->string('payment_type')->default('draft_order')->after('subscription_contract_id');
                }
            });
        }

        if (Schema::hasTable('buylater_settings')) {
            Schema::table('buylater_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('buylater_settings', 'selling_plan_group_id')) {
                    $table->string('selling_plan_group_id')->nullable();
                }
                if (!Schema::hasColumn('buylater_settings', 'selling_plan_id')) {
                    $table->string('selling_plan_id')->nullable();
                }
                if (!Schema::hasColumn('buylater_settings', 'use_selling_plan')) {
                    $table->boolean('use_selling_plan')->default(false);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn([
                    'selling_plan_id',
                    'selling_plan_group_id',
                    'subscription_contract_id',
                    'payment_type',
                ]);
            });
        }

        if (Schema::hasTable('buylater_settings')) {
            Schema::table('buylater_settings', function (Blueprint $table) {
                $table->dropColumn([
                    'selling_plan_group_id',
                    'use_selling_plan',
                ]);
            });
        }
    }
};
