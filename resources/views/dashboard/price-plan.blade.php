@extends('shopify-app::layouts.default')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-main: #f6f6f7;
        --bg-card: #ffffff;
        --text-main: #202223;
        --text-muted: #6d7175;
        --border-color: #e1e3e5;
        --primary-color: #008060;
        --primary-hover: #006e52;
        --secondary-color: #108043;
        --accent-blue: #005ea2;
        --danger-color: #d82c0d;
        --warning-color: #b98900;
    }

    body {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background-color: var(--bg-main);
        color: var(--text-main);
        margin: 0;
        padding: 24px;
    }

    .page-container {
        max-width: 900px;
        margin: 0 auto;
    }

    .page-header {
        margin-bottom: 32px;
    }

    .page-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-main);
        margin: 0 0 8px 0;
    }

    .page-header p {
        color: var(--text-muted);
        font-size: 15px;
        margin: 0;
    }

    .plan-card {
        background: var(--bg-card);
        border: 2px solid var(--border-color);
        border-radius: 16px;
        padding: 32px;
        text-align: center;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .plan-card.free {
        border-color: #e1e3e5;
    }

    .plan-card.pro {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 1px var(--primary-color), 0 8px 24px rgba(0, 128, 96, 0.15);
    }

    .plan-card.pro::before {
        content: "RECOMMENDED";
        position: absolute;
        top: 16px;
        right: -28px;
        background: var(--primary-color);
        color: white;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1px;
        padding: 4px 36px;
        transform: rotate(45deg);
    }

    .plan-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 16px;
    }

    .plan-badge.free {
        background: #f6f6f7;
        color: #6d7175;
        border: 1px solid #e1e3e5;
    }

    .plan-badge.pro {
        background: #e6f4f1;
        color: var(--primary-color);
        border: 1px solid var(--primary-color);
    }

    .plan-price {
        font-size: 48px;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1;
        margin: 16px 0 4px;
    }

    .plan-price span {
        font-size: 20px;
        font-weight: 500;
        vertical-align: super;
        color: var(--text-muted);
    }

    .plan-interval {
        color: var(--text-muted);
        font-size: 14px;
        margin-bottom: 24px;
    }

    .plan-features {
        list-style: none;
        padding: 0;
        margin: 0 0 28px;
        text-align: left;
    }

    .plan-features li {
        padding: 10px 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 14px;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .plan-features li:last-child {
        border-bottom: none;
    }

    .feat-check {
        color: var(--primary-color);
        font-size: 16px;
        flex-shrink: 0;
    }

    .feat-cross {
        color: #ccc;
        font-size: 16px;
        flex-shrink: 0;
    }

    .plan-cta {
        display: block;
        width: 100%;
        padding: 13px 20px;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        text-align: center;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
        box-sizing: border-box;
    }

    .plan-cta.free-btn {
        background: #f6f6f7;
        color: var(--text-muted);
        border: 1px solid #e1e3e5;
    }

    .plan-cta.pro-btn {
        background: var(--primary-color);
        color: white;
        border: none;
    }

    .plan-cta.pro-btn:hover {
        background: var(--primary-hover);
    }

    .plans-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 40px;
    }

    .faq-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 32px;
    }

    .faq-section h2 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-main);
        margin: 0 0 24px;
    }

    .faq-item {
        padding: 16px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .faq-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .faq-item h3 {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-main);
        margin: 0 0 8px;
    }

    .faq-item p {
        font-size: 14px;
        color: var(--text-muted);
        margin: 0;
        line-height: 1.6;
    }

    @media (max-width: 768px) {
        .plans-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1>💎 Price Plan</h1>
        <p>Choose the plan that's right for your store. Upgrade or downgrade at any time.</p>
    </div>

    <div class="plans-grid">
        <!-- Free Plan -->
        <div class="plan-card free">
            <span class="plan-badge free">Free</span>
            <div class="plan-price"><span></span>$0</div>
            <div class="plan-interval">forever free</div>
            <ul class="plan-features">
                <li><span class="feat-check">✓</span> Remind Me Later (unlimited)</li>
                <li><span class="feat-check">✓</span> Price Drop Alerts (unlimited)</li>
                <li><span class="feat-check">✓</span> Dashboard Analytics</li>
                <li><span class="feat-check">✓</span> Email Reminders</li>
                <li><span class="feat-cross">✕</span> <span style="color:#aaa;">Deposit Hold Bookings</span></li>
                <li><span class="feat-cross">✕</span> <span style="color:#aaa;">Send Balance Reminders</span></li>
            </ul>
            <span class="plan-cta free-btn">Current Plan</span>
        </div>

        <!-- Pro Plan -->
        <div class="plan-card pro">
            <span class="plan-badge pro">Pro</span>
            <div class="plan-price"><span>$</span>5</div>
            <div class="plan-interval">per month</div>
            <ul class="plan-features">
                <li><span class="feat-check">✓</span> Everything in Free</li>
                <li><span class="feat-check">✓</span> Deposit Hold Bookings</li>
                <li><span class="feat-check">✓</span> Send Balance Reminder Emails</li>
                <li><span class="feat-check">✓</span> Booking Status Tracking</li>
                <li><span class="feat-check">✓</span> Priority Support</li>
                <li><span class="feat-check">✓</span> Unlimited Events</li>
            </ul>
            <a href="{{ route('billing', array_merge(['plan' => 1], request()->query())) }}" target="_top" class="plan-cta pro-btn">
                Upgrade to Pro →
            </a>
        </div>
    </div>

    <div class="faq-section">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-item">
            <h3>Can I cancel anytime?</h3>
            <p>Yes! You can cancel or downgrade your Pro plan at any time from the dashboard. No long-term commitments.</p>
        </div>
        <div class="faq-item">
            <h3>What happens to my data if I downgrade?</h3>
            <p>All your existing data (bookings, reminders, subscribers) will be retained. You simply lose access to Pro-only features like new deposit bookings.</p>
        </div>
        <div class="faq-item">
            <h3>Is there a free trial?</h3>
            <p>The Free plan is available indefinitely with core features. Pro is $5/month with no free trial, but you can cancel anytime.</p>
        </div>
    </div>
</div>

<script>
  (function() {
    var host = new URLSearchParams(window.location.search).get('host');
    if (!host) return;
    var shopifyBridge = window['shopify-app-bridge'];
    if (!shopifyBridge) return;
    var app = shopifyBridge.createApp({ apiKey: '39b4ee2ef0ed6c2273df208b36a059fd', host: host });
    shopifyBridge.NavigationMenu.create(app, {
      items: [
        { label: 'Price Plan', destination: '/admin/price-plan' },
        { label: 'How to Use', destination: '/admin/how-to-use' },
      ],
      active: { label: 'Price Plan', destination: '/admin/price-plan' },
    });
  })();
</script>
@endsection
