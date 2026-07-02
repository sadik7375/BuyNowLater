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
        Schema::create('buylater_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->text('sendgrid_api_key')->nullable();
            $table->string('sendgrid_from_email')->nullable();
            $table->string('button_text')->default('Buy Later');
            $table->string('button_color')->default('#000000');
            $table->string('button_text_color')->default('#ffffff');
            $table->string('reminder_email_subject')->default('Reminder: You wanted to buy this later!');
            $table->text('reminder_email_template')->nullable();
            $table->string('discount_email_subject')->default('Price Drop Alert: A product you wanted is now on sale!');
            $table->text('discount_email_template')->nullable();
            $table->timestamps();

            $table->foreign('shop_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('buylater_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('product_id');
            $table->string('product_title');
            $table->string('product_handle');
            $table->text('product_image')->nullable();
            $table->string('product_price');
            $table->string('email');
            $table->dateTime('scheduled_at');
            $table->string('token')->unique();
            $table->string('status')->default('pending'); // pending, sent, cancelled
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('shop_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('buylater_subscribers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('product_id');
            $table->string('product_title');
            $table->string('product_handle');
            $table->text('product_image')->nullable();
            $table->string('product_price');
            $table->string('email');
            $table->string('status')->default('active'); // active, notified, cancelled
            $table->dateTime('notified_at')->nullable();
            $table->timestamps();

            $table->foreign('shop_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buylater_subscribers');
        Schema::dropIfExists('buylater_reminders');
        Schema::dropIfExists('buylater_settings');
    }
};
