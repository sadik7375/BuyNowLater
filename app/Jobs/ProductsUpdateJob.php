<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Subscriber;
use App\Models\Setting;
use App\Services\SendGridService;
use stdClass;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProductsUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Shop's myshopify domain
     *
     * @var ShopDomain|string
     */
    public $shopDomain;

    /**
     * The webhook data
     *
     * @var object
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param string   $shopDomain The shop's myshopify domain.
     * @param stdClass $data       The webhook data (JSON decoded).
     *
     * @return void
     */
    public function __construct($shopDomain, $data)
    {
        $this->shopDomain = $shopDomain;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Convert domain to native string
        $this->shopDomain = ShopDomain::fromNative($this->shopDomain)->toNative();

        Log::info("ProductsUpdateJob: Processing for shop {$this->shopDomain}", ['product_id' => $this->data->id ?? null]);

        // Find the shop in the database
        $shop = User::where('name', $this->shopDomain)->first();
        if (!$shop) {
            Log::error("ProductsUpdateJob: Shop not found in DB: {$this->shopDomain}");
            return;
        }

        $productId = (string) ($this->data->id ?? '');
        if (empty($productId)) {
            Log::error("ProductsUpdateJob: Missing product ID in payload.");
            return;
        }

        // Find all active subscribers for this product
        $subscribers = Subscriber::where('shop_id', $shop->id)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->get();

        if ($subscribers->isEmpty()) {
            Log::info("ProductsUpdateJob: No active subscribers for product ID {$productId} on shop {$this->shopDomain}");
            return;
        }

        // Find the lowest price among the product's variants in the webhook
        $newPrice = null;
        if (!empty($this->data->variants)) {
            foreach ($this->data->variants as $variant) {
                $price = (float) ($variant->price ?? 0);
                if ($newPrice === null || $price < $newPrice) {
                    $newPrice = $price;
                }
            }
        }

        if ($newPrice === null) {
            Log::error("ProductsUpdateJob: Could not determine new price from variants.");
            return;
        }

        Log::info("ProductsUpdateJob: Product ID {$productId} price updated. Lowest current price: {$newPrice}");

        // Load settings
        $setting = Setting::where('shop_id', $shop->id)->first();
        $apiKey = $setting->sendgrid_api_key ?? config('services.sendgrid.api_key');
        $fromEmail = $setting->sendgrid_from_email ?? config('services.sendgrid.from_email');

        $subject = $setting->discount_email_subject ?? 'Price Drop Alert: A product you wanted is now on sale!';
        $htmlTemplate = $setting->discount_email_template ?? $this->getDefaultHtmlTemplate();

        foreach ($subscribers as $subscriber) {
            $oldPrice = (float) $subscriber->product_price;

            // Trigger notification if the new price is lower than the price when they subscribed
            if ($newPrice < $oldPrice) {
                Log::info("ProductsUpdateJob: Price drop detected for subscriber {$subscriber->email}. Old: {$oldPrice}, New: {$newPrice}");

                // Format link and image
                $productLink = "https://{$this->shopDomain}/products/{$subscriber->product_handle}";
                $productImageTag = $subscriber->product_image 
                    ? '<img class="product-img" src="' . htmlspecialchars($subscriber->product_image) . '" alt="' . htmlspecialchars($subscriber->product_title) . '">'
                    : '';

                $replacements = [
                    '{product_title}' => htmlspecialchars($subscriber->product_title),
                    '{old_price}' => '$' . number_format($oldPrice, 2),
                    '{new_price}' => '$' . number_format($newPrice, 2),
                    '{product_link}' => $productLink,
                    '{product_image_tag}' => $productImageTag,
                ];

                $htmlContent = strtr($htmlTemplate, $replacements);

                $success = SendGridService::send($apiKey, $fromEmail, $subscriber->email, $subject, $htmlContent);

                if ($success) {
                    $subscriber->update([
                        'status' => 'notified',
                        'notified_at' => Carbon::now()
                    ]);
                    Log::info("ProductsUpdateJob: Successfully notified {$subscriber->email} of price drop.");
                } else {
                    Log::error("ProductsUpdateJob: Failed to send price drop email to {$subscriber->email}");
                }
            }
        }
    }

    /**
     * Get default HTML template for price drop email.
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
    .header { font-size: 24px; font-weight: bold; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px; letter-spacing: -0.02em; color: #000; }
    .product-details { display: flex; align-items: center; border: 1px solid #f0f0f0; border-radius: 8px; padding: 15px; margin-bottom: 25px; background-color: #fafafa; }
    .product-img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; margin-right: 20px; }
    .product-info h3 { margin: 0 0 5px 0; font-size: 16px; font-weight: 600; }
    .product-info p { margin: 0; color: #666; font-size: 14px; }
    .price-box { margin-top: 5px; font-size: 14px; }
    .old-price { text-decoration: line-through; color: #888; margin-right: 10px; }
    .new-price { color: #d9534f; font-weight: bold; font-size: 16px; }
    .actions { margin-bottom: 25px; }
    .btn { display: inline-block; padding: 12px 24px; background: #d9534f; color: #fff !important; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; }
    .footer { font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 20px; text-align: center; }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="header">Price Drop Alert!</div>
    <p>Hi there,</p>
    <p>Good news! A product you saved for later is now on sale. Check out the price drop below!</p>
    
    <div class="product-details">
      {product_image_tag}
      <div class="product-info">
        <h3>{product_title}</h3>
        <div class="price-box">
          <span class="old-price">{old_price}</span>
          <span class="new-price">{new_price}</span>
        </div>
      </div>
    </div>
    
    <div class="actions">
      <a href="{product_link}" class="btn">Get Discount Now</a>
    </div>
    
    <div class="footer">
      You are receiving this because you subscribed to price drop alerts for this product.
    </div>
  </div>
</body>
</html>';
    }
}
