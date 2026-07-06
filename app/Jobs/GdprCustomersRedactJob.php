<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Reminder;
use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class GdprCustomersRedactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $shopDomain;
    public $data;

    public function __construct($shopDomain, $data)
    {
        $this->shopDomain = $shopDomain;
        $this->data = $data;
    }

    public function handle()
    {
        Log::info("GDPR Customers Redact received for shop {$this->shopDomain}", (array)$this->data);

        $customerEmail = $this->data->customer->email ?? null;
        if (!$customerEmail) {
            return;
        }

        // Anonymize/Redact customer email and details
        Booking::where('email', $customerEmail)->update([
            'email' => 'redacted@gdpr.com',
            'customer_name' => 'Redacted'
        ]);

        Reminder::where('email', $customerEmail)->update([
            'email' => 'redacted@gdpr.com'
        ]);

        Subscriber::where('email', $customerEmail)->delete();

        Log::info("GDPR Customers Redact completed for customer email: {$customerEmail}");
    }
}
