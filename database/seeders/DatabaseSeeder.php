<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \Osiset\ShopifyApp\Storage\Models\Plan::updateOrCreate(
            ['id' => 1],
            [
                'type' => \Osiset\ShopifyApp\Objects\Enums\PlanType::RECURRING()->toNative(),
                'name' => 'Pro Plan',
                'price' => 5.00,
                'interval' => \Osiset\ShopifyApp\Objects\Enums\PlanInterval::EVERY_30_DAYS()->toNative(),
                'capped_amount' => null,
                'terms' => 'Unlimited reminders and discount alerts, booking enabled',
                'trial_days' => 0,
                'test' => true,
                'on_install' => false,
            ]
        );
    }
}
