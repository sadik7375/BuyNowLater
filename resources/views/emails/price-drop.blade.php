<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Price Drop Alert!</title>
<style>
  body { margin: 0; padding: 0; background-color: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .wrapper { background-color: #f0f2f5; padding: 40px 20px; }
  .container { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg, #0f172a 0%, #064e3b 100%); padding: 36px 40px; text-align: center; }
  .header-badge { display: inline-block; background: rgba(16,185,129,0.2); color: #6ee7b7; font-size: 12px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; padding: 6px 14px; border-radius: 20px; margin-bottom: 16px; }
  .header h1 { margin: 0; color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: -0.02em; }
  .header p { margin: 8px 0 0; color: #a7f3d0; font-size: 14px; }
  .body { padding: 36px 40px; }
  .product-card { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 28px; }
  .product-img { width: 100%; height: 200px; object-fit: cover; background: #f8fafc; display: block; }
  .product-info { padding: 20px; }
  .product-title { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 12px; }
  .price-row { display: flex; align-items: center; gap: 12px; }
  .old-price { font-size: 16px; color: #94a3b8; text-decoration: line-through; }
  .new-price { font-size: 24px; font-weight: 800; color: #10b981; }
  .savings-badge { background: #ecfdf5; color: #059669; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 6px; }
  .cta-btn { display: block; background: linear-gradient(135deg, #10b981, #059669); color: #ffffff !important; text-decoration: none; text-align: center; padding: 16px 32px; border-radius: 10px; font-size: 16px; font-weight: 700; letter-spacing: -0.01em; margin-bottom: 16px; }
  .divider { border: none; border-top: 1px solid #f1f5f9; margin: 28px 0; }
  .footer { background: #f8fafc; padding: 24px 40px; text-align: center; }
  .footer p { margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.6; }
  .footer a { color: #10b981; text-decoration: none; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div class="header-badge">🔥 Price Drop</div>
      <h1>The price just dropped!</h1>
      <p>A product on your watchlist is now cheaper.</p>
    </div>

    <!-- Body -->
    <div class="body">
      <p style="color:#475569; font-size:15px; margin:0 0 24px; line-height:1.6;">
        Great news! 🎉 <strong style="color:#0f172a;">{{ $subscriber->product_title }}</strong>
        that you've been watching just had a price drop. Don't miss this deal!
      </p>

      <!-- Product Card -->
      <div class="product-card">
        @if($subscriber->product_image)
          <img class="product-img" src="{{ $subscriber->product_image }}" alt="{{ $subscriber->product_title }}">
        @endif
        <div class="product-info">
          <p class="product-title">{{ $subscriber->product_title }}</p>
          <div class="price-row">
            <span class="old-price">${{ number_format((float)$subscriber->product_price, 2) }}</span>
            <span class="new-price">${{ number_format($newPrice, 2) }}</span>
            @php
              $savings = (float)$subscriber->product_price - $newPrice;
              $pct = $subscriber->product_price > 0 ? round(($savings / $subscriber->product_price) * 100) : 0;
            @endphp
            @if($pct > 0)
              <span class="savings-badge">-{{ $pct }}% OFF</span>
            @endif
          </div>
        </div>
      </div>

      <!-- CTA -->
      <a href="{{ $productUrl }}" class="cta-btn">🛒 &nbsp; Buy Now at ${{ number_format($newPrice, 2) }}</a>

      <hr class="divider">

      <p style="color:#94a3b8; font-size:13px; text-align:center; margin:0;">
        You subscribed to price alerts for this product. Prices may change at any time.
      </p>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p>
        Sent by <strong>{{ $senderName }}</strong> via BuyLater.<br>
        You're receiving this because you signed up for price alerts.
      </p>
    </div>
  </div>
</div>
</body>
</html>
