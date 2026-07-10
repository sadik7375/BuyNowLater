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
        --accent-blue: #005ea2;
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

    .steps-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 32px;
    }

    .step-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 24px;
        position: relative;
    }

    .step-number {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        font-size: 16px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 14px;
    }

    .step-card h3 {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-main);
        margin: 0 0 8px;
    }

    .step-card p {
        font-size: 14px;
        color: var(--text-muted);
        margin: 0;
        line-height: 1.6;
    }

    .step-icon {
        font-size: 28px;
        margin-bottom: 12px;
        display: block;
    }

    .benefits-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 32px;
        margin-bottom: 32px;
    }

    .benefits-section h2 {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-main);
        margin: 0 0 24px;
    }

    .benefit-item {
        display: flex;
        gap: 16px;
        align-items: flex-start;
        padding: 16px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .benefit-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .benefit-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }

    .benefit-icon.green { background: #e6f4f1; }
    .benefit-icon.blue { background: #e6f2ff; }
    .benefit-icon.orange { background: #fff5e6; }
    .benefit-icon.purple { background: #f5e6ff; }

    .benefit-content h3 {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-main);
        margin: 0 0 4px;
    }

    .benefit-content p {
        font-size: 14px;
        color: var(--text-muted);
        margin: 0;
        line-height: 1.6;
    }

    .how-to-section {
        background: linear-gradient(135deg, #e6f4f1, #f0faf6);
        border: 1px solid #b2ddd2;
        border-radius: 16px;
        padding: 32px;
    }

    .how-to-section h2 {
        font-size: 22px;
        font-weight: 700;
        color: var(--primary-color);
        margin: 0 0 8px;
    }

    .how-to-section > p {
        font-size: 14px;
        color: #4a7c6f;
        margin: 0 0 24px;
    }

    .how-to-steps {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .how-to-step {
        display: flex;
        gap: 16px;
        align-items: flex-start;
        background: white;
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid #b2ddd2;
    }

    .how-to-step-num {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        font-size: 13px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .how-to-step-content h4 {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-main);
        margin: 0 0 4px;
    }

    .how-to-step-content p {
        font-size: 13px;
        color: var(--text-muted);
        margin: 0;
        line-height: 1.5;
    }

    @media (max-width: 768px) {
        .steps-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1>📖 How to Use & Benefit</h1>
        <p>Learn how BuyNowLater works and how it helps you recover revenue from undecided customers.</p>
    </div>

    <!-- How It Works Steps -->
    <div class="steps-grid">
        <div class="step-card">
            <span class="step-icon">🛍️</span>
            <div class="step-number">1</div>
            <h3>Customer Finds a Product</h3>
            <p>A shopper visits your store but isn't ready to purchase. They see the <strong>"Buy Later"</strong> button on the product page.</p>
        </div>

        <div class="step-card">
            <span class="step-icon">🔔</span>
            <div class="step-number">2</div>
            <h3>Sets a Reminder or Alert</h3>
            <p>The customer chooses to get an email reminder at a specific time, or sign up for a price drop alert for that product.</p>
        </div>

        <div class="step-card">
            <span class="step-icon">💳</span>
            <div class="step-number">3</div>
            <h3>Places a Deposit Hold (Pro)</h3>
            <p>With the Pro plan, customers can hold an item by paying a small deposit (e.g. 10%) and pay the remainder later.</p>
        </div>

        <div class="step-card">
            <span class="step-icon">✅</span>
            <div class="step-number">4</div>
            <h3>Completes the Purchase</h3>
            <p>You send a balance reminder or price alert. The customer returns and completes their order — revenue recovered!</p>
        </div>
    </div>

    <!-- Benefits -->
    <div class="benefits-section">
        <h2>🚀 Key Benefits for Your Store</h2>

        <div class="benefit-item">
            <div class="benefit-icon green">💰</div>
            <div class="benefit-content">
                <h3>Recover Lost Revenue</h3>
                <p>Customers who leave without buying are reminded to return. Turn window shoppers into actual buyers with timely email nudges.</p>
            </div>
        </div>

        <div class="benefit-item">
            <div class="benefit-icon blue">📊</div>
            <div class="benefit-content">
                <h3>Know What Products Are Trending</h3>
                <p>Your dashboard shows you which products customers are most interested in — helping you plan inventory and promotions better.</p>
            </div>
        </div>

        <div class="benefit-item">
            <div class="benefit-icon orange">⏰</div>
            <div class="benefit-content">
                <h3>Scheduled Reminder Emails</h3>
                <p>Customers pick their own reminder time. You send the email automatically. No manual work required on your end.</p>
            </div>
        </div>

        <div class="benefit-item">
            <div class="benefit-icon purple">🏷️</div>
            <div class="benefit-content">
                <h3>Price Drop Alerts Drive Sales</h3>
                <p>When a price-sensitive customer signs up for alerts, any discount on that product triggers an instant email — bringing them back to buy.</p>
            </div>
        </div>
    </div>

    <!-- Quick Setup Guide -->
    <div class="how-to-section">
        <h2>⚡ Quick Setup Guide</h2>
        <p>Get up and running in under 5 minutes.</p>
        <div class="how-to-steps">
            <div class="how-to-step">
                <div class="how-to-step-num">1</div>
                <div class="how-to-step-content">
                    <h4>Install the Widget on Your Theme</h4>
                    <p>Go to your Shopify admin → Online Store → Themes → Customize. Add the <strong>BuyNowLater Widget</strong> block to your product page template.</p>
                </div>
            </div>
            <div class="how-to-step">
                <div class="how-to-step-num">2</div>
                <div class="how-to-step-content">
                    <h4>Configure Settings in Dashboard</h4>
                    <p>Go to the <strong>Settings</strong> tab in your dashboard. Set your button text, deposit percentage, and enable the features you want.</p>
                </div>
            </div>
            <div class="how-to-step">
                <div class="how-to-step-num">3</div>
                <div class="how-to-step-content">
                    <h4>Add Your Email Credentials</h4>
                    <p>Enter your SendGrid API key in settings to enable automated reminder and alert emails to customers.</p>
                </div>
            </div>
            <div class="how-to-step">
                <div class="how-to-step-num">4</div>
                <div class="how-to-step-content">
                    <h4>Monitor from Dashboard</h4>
                    <p>Track all reminders, price alert subscribers, and bookings from your main dashboard. Send manual reminders anytime.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
