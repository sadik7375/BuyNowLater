<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Reminder is Here!</title>
<style>
  body { margin: 0; padding: 0; background-color: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .wrapper { background-color: #f0f2f5; padding: 40px 20px; }
  .container { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 36px 40px; text-align: center; }
  .header-badge { display: inline-block; background: rgba(99,102,241,0.2); color: #a5b4fc; font-size: 12px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; padding: 6px 14px; border-radius: 20px; margin-bottom: 16px; }
  .header h1 { margin: 0; color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: -0.02em; }
  .header p { margin: 8px 0 0; color: #94a3b8; font-size: 14px; }
  .body { padding: 36px 40px; }
  .product-card { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 28px; }
  .product-img { width: 100%; height: 200px; object-fit: cover; background: #f8fafc; display: block; }
  .product-img-placeholder { width: 100%; height: 180px; background: linear-gradient(135deg, #e2e8f0, #f1f5f9); display: flex; align-items: center; justify-content: center; }
  .product-info { padding: 20px; }
  .product-title { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
  .product-price { font-size: 22px; font-weight: 800; color: #6366f1; margin: 0; }
  .cta-btn { display: block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #ffffff !important; text-decoration: none; text-align: center; padding: 16px 32px; border-radius: 10px; font-size: 16px; font-weight: 700; letter-spacing: -0.01em; margin-bottom: 20px; }
  .links-row { display: flex; gap: 12px; margin-top: 8px; }
  .link-btn { flex: 1; display: block; text-align: center; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; color: #64748b !important; }
  .divider { border: none; border-top: 1px solid #f1f5f9; margin: 28px 0; }
  .footer { background: #f8fafc; padding: 24px 40px; text-align: center; }
  .footer p { margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.6; }
  .footer a { color: #6366f1; text-decoration: none; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div class="header-badge">⏰ Your Reminder</div>
      <h1>Time to grab it!</h1>
      <p>You asked us to remind you about this product.</p>
    </div>

    <!-- Body -->
    <div class="body">
      <p style="color:#475569; font-size:15px; margin:0 0 24px; line-height:1.6;">
        Hey there! 👋 You set a reminder for <strong style="color:#0f172a;">{{ $reminder->product_title }}</strong>.
        The time has come — it's still waiting for you!
      </p>

      <!-- Product Card -->
      <div class="product-card">
        @if($reminder->product_image)
          <img class="product-img" src="{{ $reminder->product_image }}" alt="{{ $reminder->product_title }}">
        @endif
        <div class="product-info">
          <p class="product-title">{{ $reminder->product_title }}</p>
          <p class="product-price">${{ $reminder->product_price }}</p>
        </div>
      </div>

      <!-- CTA -->
      <a href="{{ $productUrl }}" class="cta-btn">🛒 &nbsp; Shop Now</a>

      <!-- Secondary actions -->
      <div class="links-row">
        <a href="{{ $rescheduleUrl }}" class="link-btn">📅 &nbsp; Remind me later</a>
        <a href="{{ $cancelUrl }}" class="link-btn">✕ &nbsp; Cancel reminder</a>
      </div>

      <hr class="divider">

      <p style="color:#94a3b8; font-size:13px; text-align:center; margin:0;">
        This reminder was set by you on {{ $reminder->created_at->format('M j, Y') }}.
      </p>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p>
        Sent by <strong>{{ $senderName }}</strong> via BuyLater.<br>
        <a href="{{ $cancelUrl }}">Unsubscribe</a> from reminders anytime.
      </p>
    </div>
  </div>
</div>
</body>
</html>
