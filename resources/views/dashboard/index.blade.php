@extends('shopify-app::layouts.default')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background-color: #0c0d0e;
        color: #f3f4f6;
        margin: 0;
        padding: 24px;
    }

    .dashboard-container {
        max-width: 1100px;
        margin: 0 auto;
    }

    /* Top Navigation bar / header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        border-bottom: 1px solid #1a1c1e;
        padding-bottom: 16px;
    }

    .dashboard-header h1 {
        font-size: 24px;
        font-weight: 700;
        margin: 0;
        color: #ffffff;
        letter-spacing: -0.03em;
    }

    .dashboard-header h1 span {
        font-weight: 300;
        color: #8e8e93;
    }

    .export-btn {
        background-color: #1c1d21;
        color: #f3f4f6;
        border: 1px solid #2d2e33;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }

    .export-btn:hover {
        background-color: #2a2b30;
    }

    /* Quick stats cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background-color: #121315;
        border: 1px solid #1c1e22;
        border-radius: 12px;
        padding: 20px;
    }

    .stat-label {
        font-size: 13px;
        color: #8a8d93;
        margin-bottom: 8px;
        font-weight: 500;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 4px;
    }

    .stat-change {
        font-size: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .change-up { color: #30d158; }
    .change-down { color: #ff453a; }

    /* Tabs navigation */
    .dashboard-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        border-bottom: 1px solid #1c1e22;
        padding-bottom: 8px;
    }

    .tab-button {
        background: none;
        border: none;
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 600;
        color: #8e8e93;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .tab-button:hover {
        color: #ffffff;
        background-color: #121315;
    }

    .tab-button.active {
        color: #ffffff;
        background-color: #1c1d21;
        border: 1px solid #2d2e33;
    }

    /* Main Grid Panels */
    .dashboard-main-grid {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    /* Card Panels */
    .panel-card {
        background-color: #121315;
        border: 1px solid #1c1e22;
        border-radius: 14px;
        padding: 24px;
    }

    .panel-card h3 {
        font-size: 16px;
        font-weight: 600;
        margin-top: 0;
        margin-bottom: 18px;
        color: #ffffff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Recent Bookings List */
    .recent-bookings-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .booking-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 14px;
        border-bottom: 1px solid #1c1e22;
    }

    .booking-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .user-avatar-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .avatar-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background-color: #1a2438;
        color: #0a84ff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
    }

    .avatar-circle.green { background-color: #162d1f; color: #30d158; }
    .avatar-circle.orange { background-color: #332115; color: #ff9f0a; }

    .user-meta h4 {
        margin: 0 0 2px 0;
        font-size: 14px;
        font-weight: 600;
        color: #ffffff;
    }

    .user-meta p {
        margin: 0;
        font-size: 12px;
        color: #8e8e93;
    }

    .booking-status-price {
        text-align: right;
    }

    .price-value {
        font-size: 14px;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 4px;
        display: block;
    }

    .status-pill {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
        text-transform: capitalize;
    }

    .status-pill.active { background-color: rgba(48, 209, 88, 0.15); color: #30d158; }
    .status-pill.expired { background-color: rgba(255, 69, 58, 0.15); color: #ff453a; }
    .status-pill.pending { background-color: rgba(255, 159, 10, 0.15); color: #ff9f0a; }

    /* Live Alerts List */
    .live-alerts-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .alert-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
    }

    .alert-product {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #ffffff;
        font-weight: 500;
    }

    .alert-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }

    .alert-dot.blue { background-color: #0a84ff; }
    .alert-dot.green { background-color: #30d158; }
    .alert-dot.orange { background-color: #ff9f0a; }

    .alert-watchers {
        color: #8e8e93;
        font-size: 13px;
    }

    .broadcast-btn {
        width: 100%;
        background-color: #0f1c30;
        color: #0a84ff;
        border: 1px solid #1b355a;
        padding: 10px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 18px;
        transition: all 0.2s ease;
    }

    .broadcast-btn:hover {
        background-color: #172c4a;
    }

    /* Progress bar wishes */
    .wishes-container {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-top: 8px;
    }

    .wish-row {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .wish-index {
        font-size: 13px;
        font-weight: 600;
        color: #8e8e93;
        width: 12px;
    }

    .wish-title {
        font-size: 13px;
        color: #f3f4f6;
        flex: 1;
        font-weight: 500;
    }

    .wish-bar-wrapper {
        flex: 1.5;
        background-color: #1c1e22;
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
        position: relative;
    }

    .wish-bar {
        background-color: #0a84ff;
        height: 100%;
        border-radius: 4px;
    }

    .wish-count {
        font-size: 13px;
        color: #ffffff;
        width: 32px;
        text-align: right;
        font-weight: 600;
    }

    /* Recovered bar charts simulation */
    .chart-bars-wrapper {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        height: 150px;
        padding-top: 20px;
    }

    .chart-bar-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        flex: 1;
    }

    .chart-bar {
        width: 80%;
        max-width: 80px;
        background-color: #0a84ff;
        border-radius: 6px;
        transition: height 0.3s ease;
        position: relative;
    }

    .chart-bar:hover::after {
        content: attr(data-value);
        position: absolute;
        top: -24px;
        left: 50%;
        transform: translateX(-50%);
        background-color: #1c1d21;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
        color: #ffffff;
        font-weight: 600;
        border: 1px solid #2d2e33;
        white-space: nowrap;
    }

    .chart-label {
        font-size: 11px;
        color: #8e8e93;
        font-weight: 500;
    }

    /* Tables */
    .panel-card-table {
        background-color: #121315;
        border: 1px solid #1c1e22;
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    th {
        font-weight: 600;
        font-size: 12px;
        color: #8e8e93;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 12px 16px;
        border-bottom: 1px solid #1c1e22;
    }

    td {
        padding: 16px;
        font-size: 14px;
        border-bottom: 1px solid #1c1e22;
        vertical-align: middle;
        color: #e5e7eb;
    }

    .product-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .product-cell img {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 6px;
        background-color: #242529;
        border: 1px solid #1c1e22;
    }

    /* Forms */
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 6px;
        color: #e5e7eb;
    }

    input[type="text"],
    input[type="email"],
    input[type="number"],
    textarea {
        width: 100%;
        padding: 10px 12px;
        background-color: #121315;
        border: 1px solid #1c1e22;
        border-radius: 6px;
        font-family: inherit;
        font-size: 14px;
        color: #ffffff;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    textarea:focus {
        border-color: #0a84ff;
        outline: none;
    }

    .color-picker-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    input[type="color"] {
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 6px;
        cursor: pointer;
        padding: 0;
        background: none;
    }

    .btn {
        background-color: #0a84ff;
        color: #ffffff;
        border: none;
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .btn:hover {
        background-color: #0076eb;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 6px;
        font-size: 14px;
        margin-bottom: 20px;
    }

    .alert.success {
        background-color: rgba(48, 209, 88, 0.15);
        color: #30d158;
        border: 1px solid rgba(48, 209, 88, 0.3);
    }
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Buy<span>Later</span> dashboard</h1>
        <button class="export-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            <span>Export</span>
        </button>
    </div>

    @if(session('success'))
        <div class="alert success">
            {{ session('success') }}
        </div>
    @endif

    <!-- 4 Stats Cards Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Revenue recovered</div>
            <div class="stat-value">${{ number_format($revenueRecovered) }}</div>
            <div class="stat-change change-up">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
                <span>+34% vs last month</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active bookings</div>
            <div class="stat-value">{{ $activeBookings }}</div>
            <div class="stat-change change-up">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
                <span>+18 this week</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Alert subscribers</div>
            <div class="stat-value">{{ number_format($alertSubscribersCount) }}</div>
            <div class="stat-change change-up">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
                <span>+209 new</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Conversion rate</div>
            <div class="stat-value">{{ $conversionRate }}%</div>
            <div class="stat-change change-down">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
                <span>-1.2% vs avg</span>
            </div>
        </div>
    </div>

    <!-- Subpage Navigation Tabs -->
    <div class="dashboard-tabs">
        <button class="tab-button active" onclick="switchTab(event, 'tab-overview')">📊 Overview Dashboard</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-bookings-list')">💰 Bookings & Deposits</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-reminders-list')">⏰ Reminders</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-subscribers-list')">🔔 Price Alerts</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-settings')">⚙️ Settings</button>
    </div>

    <!-- Tab 1: Overview Dashboard -->
    <div id="tab-overview" class="tab-content" style="display: block;">
        <div class="dashboard-main-grid">
            <!-- Recent Bookings -->
            <div class="panel-card">
                <h3>Recent bookings</h3>
                <div class="recent-bookings-list">
                    @if($bookings->isEmpty())
                        <!-- Mockup items matching the user's screenshot exactly -->
                        <div class="booking-item">
                            <div class="user-avatar-info">
                                <div class="avatar-circle">SR</div>
                                <div class="user-meta">
                                    <h4>Sarah R.</h4>
                                    <p>Sony WH-1000XM5</p>
                                </div>
                            </div>
                            <div class="booking-status-price">
                                <span class="price-value">$55.80</span>
                                <span class="status-pill active">Active</span>
                            </div>
                        </div>
                        <div class="booking-item">
                            <div class="user-avatar-info">
                                <div class="avatar-circle green">MK</div>
                                <div class="user-meta">
                                    <h4>Mohammed K.</h4>
                                    <p>Nike Air Max 2025</p>
                                </div>
                            </div>
                            <div class="booking-status-price">
                                <span class="price-value">$31.20</span>
                                <span class="status-pill active">Active</span>
                            </div>
                        </div>
                        <div class="booking-item">
                            <div class="user-avatar-info">
                                <div class="avatar-circle orange">AL</div>
                                <div class="user-meta">
                                    <h4>Aisha L.</h4>
                                    <p>Apple Watch SE</p>
                                </div>
                            </div>
                            <div class="booking-status-price">
                                <span class="price-value">$62.50</span>
                                <span class="status-pill pending">Exp. soon</span>
                            </div>
                        </div>
                        <div class="booking-item">
                            <div class="user-avatar-info">
                                <div class="avatar-circle">JT</div>
                                <div class="user-meta">
                                    <h4>James T.</h4>
                                    <p>Dyson Airwrap</p>
                                </div>
                            </div>
                            <div class="booking-status-price">
                                <span class="price-value">$110.00</span>
                                <span class="status-pill active">Active</span>
                            </div>
                        </div>
                    @else
                        @foreach($bookings->take(5) as $booking)
                            <div class="booking-item">
                                <div class="user-avatar-info">
                                    <div class="avatar-circle">{{ strtoupper(substr($booking->email, 0, 2)) }}</div>
                                    <div class="user-meta">
                                        <h4>{{ $booking->email }}</h4>
                                        <p>{{ $booking->product_title }}</p>
                                    </div>
                                </div>
                                <div class="booking-status-price">
                                    <span class="price-value">${{ number_format($booking->deposit_amount, 2) }}</span>
                                    <span class="status-pill active">{{ $booking->status }}</span>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- Live Alerts -->
            <div class="panel-card">
                <h3>Live alerts</h3>
                <div class="live-alerts-list">
                    @php $colors = ['blue', 'green', 'orange', 'blue']; $i = 0; @endphp
                    @foreach($liveAlerts as $product => $count)
                        <div class="alert-item">
                            <span class="alert-product">
                                <span class="alert-dot {{ $colors[$i % 4] }}"></span>
                                {{ $product }}
                            </span>
                            <span class="alert-watchers">{{ $count }} watching</span>
                        </div>
                        @php $i++; @endphp
                    @endforeach
                </div>
                <button class="broadcast-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11a9 9 0 0 1 9 9"></path><path d="M4 4a16 16 0 0 1 16 16"></path><circle cx="5" cy="19" r="1"></circle></svg>
                    <span>Broadcast sale to all subscribers</span>
                </button>
            </div>
        </div>

        <!-- Row 2: Wishes and Recovered Charts -->
        <div class="dashboard-main-grid">
            <!-- Most Wished Products -->
            <div class="panel-card">
                <h3>Most wished products</h3>
                <div class="wishes-container">
                    @php $idx = 1; @endphp
                    @foreach($wishes as $product => $count)
                        <div class="wish-row">
                            <span class="wish-index">{{ $idx }}</span>
                            <span class="wish-title">{{ $product }}</span>
                            <div class="wish-bar-wrapper">
                                <div class="wish-bar" style="width: {{ min(100, max(15, ($count / 350) * 100)) }}%;"></div>
                            </div>
                            <span class="wish-count">{{ $count }}</span>
                        </div>
                        @php $idx++; @endphp
                    @endforeach
                </div>
            </div>

            <!-- Revenue Recovered Chart simulation -->
            <div class="panel-card">
                <h3>Revenue recovered this month</h3>
                <div class="chart-bars-wrapper">
                    <div class="chart-bar-container">
                        <div class="chart-bar" style="height: 40px;" data-value="$1,200"></div>
                        <span class="chart-label">Week 1</span>
                    </div>
                    <div class="chart-bar-container">
                        <div class="chart-bar" style="height: 70px;" data-value="$2,400"></div>
                        <span class="chart-label">Week 2</span>
                    </div>
                    <div class="chart-bar-container">
                        <div class="chart-bar" style="height: 90px;" data-value="$3,100"></div>
                        <span class="chart-label">Week 3</span>
                    </div>
                    <div class="chart-bar-container">
                        <div class="chart-bar" style="height: 115px;" data-value="$4,820"></div>
                        <span class="chart-label">Week 4</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Bookings List -->
    <div id="tab-bookings-list" class="tab-content" style="display: none;">
        <div class="panel-card-table">
            <h3>Bookings & Deposit Holds</h3>
            <div class="table-responsive">
                @if($bookings->isEmpty())
                    <p style="color: #8e8e93; text-align: center; padding: 20px 0;">No bookings created yet.</p>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Email</th>
                                <th>Product Price</th>
                                <th>Deposit paid</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bookings as $booking)
                                <tr>
                                    <td>{{ $booking->product_title }}</td>
                                    <td>{{ $booking->email }}</td>
                                    <td>${{ number_format($booking->product_price, 2) }}</td>
                                    <td>${{ number_format($booking->deposit_amount, 2) }}</td>
                                    <td><span class="status-pill {{ $booking->status }}">{{ $booking->status }}</span></td>
                                    <td>{{ $booking->created_at->format('M j, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <!-- Tab 3: Reminders List -->
    <div id="tab-reminders-list" class="tab-content" style="display: none;">
        <div class="panel-card-table">
            <h3>Scheduled Reminders</h3>
            <div class="table-responsive">
                @if($reminders->isEmpty())
                    <p style="color: #8e8e93; text-align: center; padding: 20px 0;">No customer reminders scheduled yet.</p>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Customer Email</th>
                                <th>Scheduled At</th>
                                <th>Status</th>
                                <th>Sent At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reminders as $reminder)
                                <tr>
                                    <td>{{ $reminder->product_title }}</td>
                                    <td>{{ $reminder->email }}</td>
                                    <td>{{ $reminder->scheduled_at->format('M j, Y g:i a') }}</td>
                                    <td><span class="status-pill {{ $reminder->status }}">{{ $reminder->status }}</span></td>
                                    <td>{{ $reminder->sent_at ? $reminder->sent_at->format('M j, Y g:i a') : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <!-- Tab 4: Price Alerts -->
    <div id="tab-subscribers-list" class="tab-content" style="display: none;">
        <div class="panel-card-table">
            <h3>Price Drop Subscribers</h3>
            <div class="table-responsive">
                @if($subscribers->isEmpty())
                    <p style="color: #8e8e93; text-align: center; padding: 20px 0;">No price drop subscriptions active.</p>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Email</th>
                                <th>Original Price</th>
                                <th>Status</th>
                                <th>Subscribed Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($subscribers as $subscriber)
                                <tr>
                                    <td>{{ $subscriber->product_title }}</td>
                                    <td>{{ $subscriber->email }}</td>
                                    <td>${{ number_format((float)$subscriber->product_price, 2) }}</td>
                                    <td><span class="status-pill {{ $subscriber->status }}">{{ $subscriber->status }}</span></td>
                                    <td>{{ $subscriber->created_at->format('M j, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <!-- Tab 5: Settings -->
    <div id="tab-settings" class="tab-content" style="display: none;">
        <form action="{{ route('settings.save', request()->query()) }}" method="POST">
            @csrf
            <input type="hidden" name="token" class="session-token" value="">

            <!-- Card 1: Storefront Widget -->
            <div class="panel-card" style="margin-bottom: 20px;">
                <h3>Storefront Widget & Payment Customization</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="button_text">Button Text</label>
                        <input type="text" id="button_text" name="button_text" value="{{ $settings->button_text }}" required>
                    </div>
                    <div class="form-group">
                        <label for="deposit_percentage">Required Deposit Percentage (%)</label>
                        <input type="number" id="deposit_percentage" name="deposit_percentage" value="{{ $settings->deposit_percentage ?? 10 }}" min="1" max="100" required>
                        <p style="font-size: 12px; color: #8e8e93; margin-top: 6px;">
                            Percentage of the product price customers pay as a deposit to reserve the product (e.g., 10 for 10%).
                        </p>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="button_color">Button Color</label>
                        <div class="color-picker-group">
                            <input type="color" id="button_color" name="button_color" value="{{ $settings->button_color }}">
                            <input type="text" id="button_color_hex" value="{{ $settings->button_color }}" oninput="syncColor(this, 'button_color')">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="button_text_color">Button Text Color</label>
                        <div class="color-picker-group">
                            <input type="color" id="button_text_color" name="button_text_color" value="{{ $settings->button_text_color }}">
                            <input type="text" id="button_text_color_hex" value="{{ $settings->button_text_color }}" oninput="syncColor(this, 'button_text_color')">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Email Sender Branding -->
            <div class="panel-card" style="margin-bottom: 20px;">
                <h3>Email Sender Branding</h3>
                <p style="font-size: 13px; color: #8e8e93; margin-bottom: 20px; margin-top: -10px;">
                    All reminder and price alert emails are sent via BuyLater's centralized email system. Customize the sender name so your customers recognize your brand.
                </p>
                <div class="form-group">
                    <label for="sender_display_name">Sender Display Name</label>
                    <input type="text" id="sender_display_name" name="sender_display_name"
                        value="{{ $settings->sender_display_name ?? ($settings->shop->name ?? 'Your Store') . ' via BuyLater' }}"
                        placeholder="NovaTrend Store via BuyLater" required maxlength="100">
                    <p style="font-size: 12px; color: #8e8e93; margin-top: 6px;">
                        This is how your name appears in your customer's inbox — e.g., <strong style="color:#f3f4f6;">NovaTrend Store via BuyLater</strong>
                    </p>
                </div>
                <div style="background:#0f1c30; border:1px solid #1b355a; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0a84ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span style="font-size:13px; color:#8e8e93;">Emails are delivered from BuyLater's verified sending domain — no API key required from you.</span>
                </div>
            </div>


            <!-- Card 3: Email templates -->
            <div class="panel-card">
                <h3>Email Templates</h3>
                <div class="form-group">
                    <label for="reminder_email_subject">Reminder Email Subject</label>
                    <input type="text" id="reminder_email_subject" name="reminder_email_subject" value="{{ $settings->reminder_email_subject }}" required>
                </div>
                <div class="form-group">
                    <label for="reminder_email_template">Reminder Email HTML (Optional)</label>
                    <textarea id="reminder_email_template" name="reminder_email_template" rows="8">{{ $settings->reminder_email_template }}</textarea>
                </div>
                <hr style="border: none; border-top: 1px solid #1c1e22; margin: 24px 0;">
                <div class="form-group">
                    <label for="discount_email_subject">Price Drop Email Subject</label>
                    <input type="text" id="discount_email_subject" name="discount_email_subject" value="{{ $settings->discount_email_subject }}" required>
                </div>
                <div class="form-group">
                    <label for="discount_email_template">Price Drop Email HTML (Optional)</label>
                    <textarea id="discount_email_template" name="discount_email_template" rows="8">{{ $settings->discount_email_template }}</textarea>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn">Save All Settings</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(event, tabId) {
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => content.style.display = 'none');

        const tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(btn => btn.classList.remove('active'));

        document.getElementById(tabId).style.display = 'block';
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('active');
        } else {
            // Find tab button that matches tabId and activate it
            const tabButton = Array.from(document.querySelectorAll('.tab-button')).find(btn => {
                const onclick = btn.getAttribute('onclick') || '';
                return onclick.includes(tabId);
            });
            if (tabButton) {
                tabButton.classList.add('active');
            }
        }
    }

    function syncColor(textInput, pickerId) {
        const picker = document.getElementById(pickerId);
        if (textInput.value.match(/^#[0-9A-F]{6}$/i)) {
            picker.value = textInput.value;
        }
    }

    document.getElementById('button_color').addEventListener('input', function() {
        document.getElementById('button_color_hex').value = this.value;
    });

    document.getElementById('button_text_color').addEventListener('input', function() {
        document.getElementById('button_text_color_hex').value = this.value;
    });

    // Automatically switch to correct tab on page load if tab param or hash exists
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        let tabParam = urlParams.get('tab');
        if (!tabParam && window.location.hash) {
            tabParam = window.location.hash.replace('#', '');
        }
        if (tabParam) {
            switchTab(null, 'tab-' + tabParam);
        }
    });
</script>
@endsection
