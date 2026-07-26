<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->text('terms_text')->nullable()->after('hold_duration_days');
        });
    }

    public function down(): void
    {
        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->dropColumn('terms_text');
        });
    }
};
