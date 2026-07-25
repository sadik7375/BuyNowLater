<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->string('sender_display_name')->nullable();
        });

        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->dropColumn('sendgrid_api_key');
        });

        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->dropColumn('sendgrid_from_email');
        });
    }

    public function down(): void
    {
        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->text('sendgrid_api_key')->nullable();
        });

        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->string('sendgrid_from_email')->nullable();
        });

        Schema::table('buylater_settings', function (Blueprint $table) {
            $table->dropColumn('sender_display_name');
        });
    }
};

