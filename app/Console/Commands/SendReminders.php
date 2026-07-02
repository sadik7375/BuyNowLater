<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Reminder;
use App\Models\Setting;
use App\Services\SendGridService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find pending product reminders scheduled for now or earlier and send emails via SendGrid';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $this->info("Checking for reminders scheduled at or before {$now->toDateTimeString()}...");

        // Fetch pending reminders where scheduled_at is in the past or now
        $reminders = Reminder::with('shop')
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($reminders->isEmpty()) {
            $this->info("No pending reminders found.");
            return 0;
        }

        $this->info("Found {$reminders->count()} pending reminder(s) to process.");

        foreach ($reminders as $reminder) {
            try {
                // Fetch shop-specific settings
                $setting = Setting::where('shop_id', $reminder->shop_id)->first();

                // Get SendGrid credentials (priority: shop-specific settings, fallback: global config)
                $apiKey = $setting->sendgrid_api_key ?? config('services.sendgrid.api_key');
                $fromEmail = $setting->sendgrid_from_email ?? config('services.sendgrid.from_email');

                if (empty($apiKey) || empty($fromEmail)) {
                    $this->error("Skipping reminder ID {$reminder->id} due to missing SendGrid API key or sender email configuration.");
                    Log::error("SendReminders Command: Missing SendGrid settings for shop ID {$reminder->shop_id}");
                    continue;
                }

                // Determine subject and template
                $subject = $setting->reminder_email_subject ?? 'Reminder: You wanted to buy this later!';
                
                // Get email template html
                $htmlTemplate = $setting->reminder_email_template ?? $this->getDefaultHtmlTemplate();

                // Build values
                $shopDomain = $reminder->shop->name; // User model 'name' attribute contains the shop domain (e.g. test.myshopify.com)
                $productLink = "https://{$shopDomain}/products/{$reminder->product_handle}";
                $cancelLink = route('reminders.cancel', ['token' => $reminder->token]);
                $rescheduleLink = route('reminders.reschedule.form', ['token' => $reminder->token]);

                $productImageTag = $reminder->product_image 
                    ? '<img class="product-img" src="' . htmlspecialchars($reminder->product_image) . '" alt="' . htmlspecialchars($reminder->product_title) . '">'
                    : '';

                // Replace placeholders
                $replacements = [
                    '{product_title}' => htmlspecialchars($reminder->product_title),
                    '{product_price}' => htmlspecialchars($reminder->product_price),
                    '{product_link}' => $productLink,
                    '{cancel_link}' => $cancelLink,
                    '{reschedule_link}' => $rescheduleLink,
                    '{product_image_tag}' => $productImageTag,
                ];

                $htmlContent = strtr($htmlTemplate, $replacements);

                $this->info("Sending email reminder to {$reminder->email} for product '{$reminder->product_title}'...");

                // Dispatch email
                $success = SendGridService::send($apiKey, $fromEmail, $reminder->email, $subject, $htmlContent);

                if ($success) {
                    $reminder->update([
                        'status' => 'sent',
                        'sent_at' => Carbon::now()
                    ]);
                    $this->info("Reminder ID {$reminder->id} sent successfully.");
                } else {
                    $this->error("Failed to send reminder ID {$reminder->id} via SendGrid API.");
                }
            } catch (\Exception $e) {
                $this->error("Error processing reminder ID {$reminder->id}: " . $e->getMessage());
                Log::error("SendReminders Command Exception: " . $e->getMessage(), ['exception' => $e]);
            }
        }

        $this->info("Reminder dispatch complete.");
        return 0;
    }

    /**
     * Get default HTML template for email reminder.
     *
     * @return string
     */
    protected function getDefaultHtmlTemplate()
    {
        return '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f6f6f6; margin: 0; padding: 0; color: #333; }
    .email-container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; padding: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .header { font-size: 24px; font-weight: bold; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px; letter-spacing: -0.02em; }
    .product-details { display: flex; align-items: center; border: 1px solid #f0f0f0; border-radius: 8px; padding: 15px; margin-bottom: 25px; background-color: #fafafa; }
    .product-img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; margin-right: 20px; }
    .product-info h3 { margin: 0 0 5px 0; font-size: 16px; font-weight: 600; }
    .product-info p { margin: 0; color: #666; font-size: 14px; }
    .actions { margin-bottom: 25px; }
    .btn { display: inline-block; padding: 12px 24px; background: #000; color: #fff !important; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; }
    .footer { font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 20px; text-align: center; }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="header">BuyLater</div>
    <p>Hi there,</p>
    <p>You asked us to remind you about the following product on our store. We wanted to let you know it is still waiting for you!</p>
    
    <div class="product-details">
      {product_image_tag}
      <div class="product-info">
        <h3>{product_title}</h3>
        <p>Price: {product_price}</p>
      </div>
    </div>
    
    <div class="actions">
      <a href="{product_link}" class="btn">View Product & Buy Now</a>
    </div>
    
    <p style="font-size:13px; color:#666; margin-top: 30px;">Need more time or changed your mind? Use the links below to update your reminder:</p>
    <div style="margin-bottom:25px;">
      <a href="{reschedule_link}" style="color:#0066cc; text-decoration:underline; font-size:13px; margin-right:15px;">Reschedule Reminder</a>
      <a href="{cancel_link}" style="color:#cc0000; text-decoration:underline; font-size:13px;">Cancel Reminder</a>
    </div>
    
    <div class="footer">
      This reminder was sent to you at your request.
    </div>
  </div>
</body>
</html>';
    }
}
