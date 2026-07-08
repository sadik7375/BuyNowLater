<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reminder: Complete Your Purchase</title>
<style>
  body { margin: 0; padding: 0; background-color: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .wrapper { background-color: #f0f2f5; padding: 40px 20px; }
  .container { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 36px 40px; text-align: center; }
  .header-badge { display: inline-block; background: rgba(16, 128, 67, 0.2); color: #8ef7bb; font-size: 12px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; padding: 6px 14px; border-radius: 20px; margin-bottom: 16px; }
  .header-badge.pending { background: rgba(99,102,241,0.2); color: #a5b4fc; }
  .header h1 { margin: 0; color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: -0.02em; }
  .header p { margin: 8px 0 0; color: #94a3b8; font-size: 14px; }
  .body { padding: 36px 40px; }
  
  /* Financials Breakdown Table */
  .financial-table { width: 100%; border-collapse: collapse; margin: 24px 0; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
  .financial-table th { background-color: #f8fafc; text-align: left; padding: 12px 16px; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid #e2e8f0; }
  .financial-table td { padding: 14px 16px; font-size: 14px; color: #0f172a; border-bottom: 1px solid #f1f5f9; }
  .financial-table tr:last-child td { border-bottom: none; font-weight: 700; background-color: #f8fafc; font-size: 15px; }
  
  .product-card { display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 28px; background-color: #fafafa; }
  .product-img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; margin-right: 16px; background-color: #ffffff; border: 1px solid #f1f5f9; }
  .product-info { flex: 1; }
  .product-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 4px; }
  .product-price-label { font-size: 13px; color: #64748b; margin: 0; }
  
  .cta-btn { display: block; background: linear-gradient(135deg, #108043, #15b05c); color: #ffffff !important; text-decoration: none; text-align: center; padding: 16px 32px; border-radius: 10px; font-size: 16px; font-weight: 700; letter-spacing: -0.01em; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(16, 128, 67, 0.2); }
  .cta-btn.pending { background: linear-gradient(135deg, #6366f1, #8b5cf6); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2); }
  
  .divider { border: none; border-top: 1px solid #f1f5f9; margin: 28px 0; }
  .footer { background: #f8fafc; padding: 24px 40px; text-align: center; }
  .footer p { margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.6; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="container">
    <!-- Header -->
    <div class="header">
      @if($isDepositPaid)
        <div class="header-badge">💰 Complete Booking</div>
        <h1>Pay Remaining Balance</h1>
        <p>Complete payment to receive your items</p>
      @else
        <div class="header-badge pending">⏰ Complete Deposit</div>
        <h1>Secure Your Booking</h1>
        <p>Pay your deposit to hold this item</p>
      @endif
    </div>

    <!-- Body -->
    <div class="body">
      <p style="color:#475569; font-size:15px; margin:0 0 20px; line-height:1.6;">
        Hi {{ htmlspecialchars($booking->customer_name ?? 'Valued Customer') }}, 👋
      </p>

      @if($isDepositPaid)
        <p style="color:#475569; font-size:15px; margin:0 0 24px; line-height:1.6;">
          Thank you for your deposit holding payment for <strong style="color:#0f172a;">{{ $booking->product_title }}</strong>! 
          To complete your order and ship your item, please pay the remaining balance using the secure checkout button below:
        </p>
      @else
        <p style="color:#475569; font-size:15px; margin:0 0 24px; line-height:1.6;">
          You requested to hold <strong style="color:#0f172a;">{{ $booking->product_title }}</strong> to buy later. 
          To secure your hold, please pay the deposit using the secure button below:
        </p>
      @endif

      <!-- Product Card -->
      <div class="product-card">
        @if($booking->product_image)
          <img class="product-img" src="{{ $booking->product_image }}" alt="{{ $booking->product_title }}">
        @endif
        <div class="product-info">
          <p class="product-title">{{ $booking->product_title }}</p>
          <p class="product-price-label">Product Price: ${{ number_format((float)$booking->product_price, 2) }}</p>
        </div>
      </div>

      <!-- Financials Table -->
      <table class="financial-table">
        <thead>
          <tr>
            <th colspan="2">Booking Details Summary</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Product Total Price</td>
            <td style="text-align: right;">${{ number_format((float)$booking->product_price, 2) }}</td>
          </tr>
          <tr>
            <td>Deposit Paid</td>
            <td style="text-align: right; color: #108043; font-weight: 600;">${{ number_format((float)$booking->deposit_amount, 2) }}</td>
          </tr>
          <tr>
            <td>Remaining Balance</td>
            <td style="text-align: right; color: #6366f1; font-weight: 700;">${{ number_format((float)$booking->remaining_balance, 2) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- CTA Button -->
      @if($isDepositPaid)
        <a href="{{ $buttonUrl }}" class="cta-btn">💳 &nbsp; Complete Remaining Payment</a>
      @else
        <a href="{{ $buttonUrl }}" class="cta-btn pending">🛒 &nbsp; Complete Deposit Payment</a>
      @endif

      <hr class="divider">

      <p style="color:#94a3b8; font-size:13px; text-align:center; margin:0;">
        Order reference token: <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">{{ $booking->token }}</code>
      </p>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p>
        Sent by <strong>{{ $senderName }}</strong> via BuyLater.<br>
        If you have any questions, please reply to this email.
      </p>
    </div>
  </div>
</div>
</body>
</html>
