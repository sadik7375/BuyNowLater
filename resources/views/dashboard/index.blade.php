@extends('shopify-app::layouts.default')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root {
        --bg-main: #f6f6f7;
        --bg-card: #ffffff;
        --text-main: #202223;
        --text-muted: #6d7175;
        --border-color: #e1e3e5;
        --primary-color: #008060; /* Shopify Green */
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

    .dashboard-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Top Navigation bar / header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 16px;
    }

    .dashboard-header h1 {
        font-size: 26px;
        font-weight: 700;
        margin: 0;
        color: var(--text-main);
        letter-spacing: -0.03em;
    }

    .dashboard-header h1 span {
        font-weight: 300;
        color: var(--text-muted);
    }

    .export-btn {
        background-color: #ffffff;
        color: var(--text-main);
        border: 1px solid var(--border-color);
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
        background-color: #f1f2f4;
    }

    /* Warning Expiring Section */
    .expiring-warning-box {
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .warning-danger {
        background-color: #fff4f2;
        border: 1px solid #ffc5bd;
        color: var(--danger-color);
    }
    .warning-amber {
        background-color: #fffaf0;
        border: 1px solid #ffe79a;
        color: #8a6d00;
    }
    .warning-title {
        font-weight: 700;
        font-size: 15px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .warning-list {
        margin: 0;
        padding-left: 20px;
        font-size: 13.5px;
    }
    .warning-list li {
        margin-bottom: 4px;
    }

    /* Quick stats cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background-color: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.01);
    }

    .stat-label {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 8px;
        font-weight: 500;
    }

    .stat-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 4px;
    }

    .stat-change {
        font-size: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .change-up { color: var(--secondary-color); }
    .change-down { color: var(--danger-color); }

    /* Tabs navigation */
    .dashboard-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 8px;
    }

    .tab-button {
        background: none;
        border: none;
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .tab-button:hover {
        color: var(--text-main);
        background-color: rgba(0,0,0,0.03);
    }

    .tab-button.active {
        color: var(--text-main);
        background-color: #ffffff;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
        background-color: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }

    .panel-card h3 {
        font-size: 16px;
        font-weight: 600;
        margin-top: 0;
        margin-bottom: 18px;
        color: var(--text-main);
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
        border-bottom: 1px solid var(--border-color);
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
        background-color: #e2f1ff;
        color: var(--accent-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
    }

    .avatar-circle.green { background-color: #e3fbeb; color: var(--secondary-color); }
    .avatar-circle.orange { background-color: #fff3e0; color: #e65100; }

    .user-meta h4 {
        margin: 0 0 2px 0;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-main);
    }

    .user-meta p {
        margin: 0;
        font-size: 12px;
        color: var(--text-muted);
    }

    .booking-status-price {
        text-align: right;
    }

    .price-value {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 4px;
        display: block;
    }

    /* Status Pills */
    .status-pill {
        display: inline-block;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 12px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .status-pill.pending { background-color: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
    .status-pill.deposit_paid { background-color: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; }
    .status-pill.completed { background-color: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
    .status-pill.expired { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

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
        color: var(--text-main);
        font-weight: 500;
    }

    .alert-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }

    .alert-dot.blue { background-color: var(--accent-blue); }
    .alert-dot.green { background-color: var(--secondary-color); }
    .alert-dot.orange { background-color: #ff9f0a; }

    .alert-watchers {
        color: var(--text-muted);
        font-size: 13px;
    }

    .broadcast-btn {
        width: 100%;
        background-color: #ffffff;
        color: var(--accent-blue);
        border: 1px solid var(--border-color);
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
        background-color: #f6f6f7;
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
        color: var(--text-muted);
        width: 12px;
    }

    .wish-title {
        font-size: 13px;
        color: var(--text-main);
        flex: 1;
        font-weight: 500;
    }

    .wish-bar-wrapper {
        flex: 1.5;
        background-color: #f1f2f4;
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
        position: relative;
    }

    .wish-bar {
        background-color: var(--accent-blue);
        height: 100%;
        border-radius: 4px;
    }

    .wish-count {
        font-size: 13px;
        color: var(--text-main);
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
        background-color: var(--primary-color);
        border-radius: 6px 6px 0 0;
        transition: height 0.3s ease;
        position: relative;
    }

    .chart-bar:hover::after {
        content: attr(data-value);
        position: absolute;
        top: -28px;
        left: 50%;
        transform: translateX(-50%);
        background-color: var(--text-main);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        color: #ffffff;
        font-weight: 600;
        white-space: nowrap;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .chart-label {
        font-size: 11px;
        color: var(--text-muted);
        font-weight: 500;
    }

    /* Tables */
    .panel-card-table {
        background-color: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
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
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
    }

    td {
        padding: 16px;
        font-size: 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
        color: var(--text-main);
    }

    /* Action Buttons inside booking rows */
    .actions-cell {
        display: flex;
        gap: 8px;
    }

    .btn-action-primary {
        background-color: var(--primary-color);
        color: #ffffff;
        border: none;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 4px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: background-color 0.2s;
    }
    .btn-action-primary:hover {
        background-color: var(--primary-hover);
    }

    .btn-action-secondary {
        background-color: #ffffff;
        color: var(--text-main);
        border: 1px solid var(--border-color);
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 4px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: background-color 0.2s;
    }
    .btn-action-secondary:hover {
        background-color: #f6f6f7;
    }

    /* Alerts */
    .alert {
        padding: 12px 16px;
        border-radius: 6px;
        font-size: 14px;
        margin-bottom: 20px;
    }

    .alert.success {
        background-color: #e3fbeb;
        color: var(--secondary-color);
        border: 1px solid rgba(48, 209, 88, 0.3);
    }
    .alert.error {
        background-color: #fff0f0;
        color: var(--danger-color);
        border: 1px solid rgba(255, 69, 58, 0.3);
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
        color: var(--text-main);
    }

    input[type="text"],
    input[type="email"],
    input[type="number"],
    textarea {
        width: 100%;
        padding: 10px 12px;
        background-color: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-family: inherit;
        font-size: 14px;
        color: var(--text-main);
        box-sizing: border-box;
        transition: border-color 0.2s;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    textarea:focus {
        border-color: var(--accent-blue);
        outline: none;
    }

    .btn-save {
        background-color: var(--primary-color);
        color: #ffffff;
        border: none;
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn-save:hover {
        background-color: var(--primary-hover);
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

    /* Segmented filter toolbar styling */
    .filter-toolbar-container {
        background-color: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 12px 20px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }

    .filter-presets {
        display: flex;
        background-color: #f1f2f4;
        padding: 4px;
        border-radius: 8px;
        gap: 4px;
    }

    .filter-presets .filter-btn {
        background: none;
        border: none;
        padding: 6px 16px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-muted);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .filter-presets .filter-btn:hover {
        color: var(--text-main);
    }

    .filter-presets .filter-btn.active {
        background-color: #ffffff;
        color: var(--text-main);
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }

    .custom-date-picker {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .custom-date-picker input[type="date"] {
        padding: 6px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 13px;
        font-family: inherit;
        color: var(--text-main);
        background-color: #ffffff;
        transition: border-color 0.2s ease;
    }

    .custom-date-picker input[type="date"]:focus {
        border-color: var(--primary-color);
        outline: none;
    }

    .custom-date-picker span {
        font-size: 13px;
        color: var(--text-muted);
        font-weight: 500;
    }

    .custom-date-picker button {
        background-color: var(--primary-color);
        color: #ffffff;
        border: none;
        padding: 7px 16px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .custom-date-picker button:hover {
        background-color: var(--primary-hover);
    }

    /* Expiring Soon styling */
    .expiry-group {
        background-color: #fcfcfc;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 12px;
        box-shadow: inset 0 0 0 1px var(--border-color);
        transition: transform 0.2s ease;
    }
    .expiry-group:hover {
        transform: translateX(4px);
    }
    .expiry-row {
        transition: background-color 0.2s ease;
    }

    @keyframes pulse {
        0% { opacity: 0.4; }
        50% { opacity: 1; }
        100% { opacity: 0.4; }
    }
    /* Table Search and Filters */
    .table-control-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        align-items: center;
        background-color: #fafbfb;
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }

    .search-input-wrapper {
        flex: 1;
        min-width: 250px;
        position: relative;
    }

    .search-input-wrapper svg {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .search-input-wrapper input {
        width: 100%;
        padding: 8px 12px 8px 36px !important;
        border: 1px solid var(--border-color) !important;
        border-radius: 6px !important;
        font-size: 13.5px !important;
        font-family: inherit;
        background-color: #ffffff;
        color: var(--text-main);
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .search-input-wrapper input:focus {
        border-color: var(--accent-blue) !important;
        box-shadow: 0 0 0 2px rgba(0, 94, 162, 0.1);
        outline: none;
    }

    .filter-select {
        padding: 8px 32px 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-size: 13.5px;
        font-family: inherit;
        background-color: #ffffff;
        color: var(--text-main);
        cursor: pointer;
        transition: border-color 0.2s;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236d7175' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 12px;
        min-width: 140px;
    }

    .filter-select:focus {
        border-color: var(--accent-blue);
        outline: none;
    }
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Buy<span>Later</span> Dashboard</h1>
    </div>
    <div class="filter-toolbar-container">
        <div class="filter-presets">
            <button class="filter-btn {{ $dateFilter == 'all' ? 'active' : '' }}" onclick="applyDateFilter('all')">All Time</button>
            <button class="filter-btn {{ $dateFilter == 'today' ? 'active' : '' }}" onclick="applyDateFilter('today')">Today</button>
            <button class="filter-btn {{ $dateFilter == 'week' ? 'active' : '' }}" onclick="applyDateFilter('week')">This Week</button>
        </div>
        <form class="custom-date-picker" onsubmit="event.preventDefault(); applyDateFilter('custom', document.getElementById('start_date_picker').value, document.getElementById('end_date_picker').value);">
            <input type="date" id="start_date_picker" name="start_date" value="{{ $start ? $start->toDateString() : '' }}" />
            <span>to</span>
            <input type="date" id="end_date_picker" name="end_date" value="{{ $end ? $end->toDateString() : '' }}" />
            <button type="submit">Apply</button>
        </form>
    </div>

    @if(session('success'))
        <div class="alert success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert error">
            {{ session('error') }}
        </div>
    @endif

    <!-- 1. "Expiring Today/Tomorrow" Warning Section -->
    @if($expiringToday->isNotEmpty())
        <div class="expiring-warning-box warning-danger">
            <div class="warning-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                🚨 Expiring Today
            </div>
            <ul class="warning-list">
                @foreach($expiringToday as $booking)
                    @php
                        $displayName = ($booking->customer_name && strtolower($booking->customer_name) !== 'n/a' && strtolower($booking->customer_name) !== 'null') ? $booking->customer_name : $booking->email;
                    @endphp
                    <li>
                        <strong>{{ $displayName }}</strong>'s reservation for <em>{{ $booking->product_title }}</em> expires today (Deposit: ${{ number_format($booking->deposit_amount, 2) }}).
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($expiringTomorrow->isNotEmpty())
        <div class="expiring-warning-box warning-amber">
            <div class="warning-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                ⚠️ Expiring Tomorrow
            </div>
            <ul class="warning-list">
                @foreach($expiringTomorrow as $booking)
                    @php
                        $displayName = ($booking->customer_name && strtolower($booking->customer_name) !== 'n/a' && strtolower($booking->customer_name) !== 'null') ? $booking->customer_name : $booking->email;
                    @endphp
                    <li>
                        <strong>{{ $displayName }}</strong>'s reservation for <em>{{ $booking->product_title }}</em> expires tomorrow.
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 4 Stats Cards Grid (Updated Card 3 with Scheduled Reminders Count) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Revenue Recovered</div>
            <div class="stat-value">${{ number_format($revenueRecovered, 2) }}</div>
            <div class="stat-change" style="color: var(--text-muted); font-size: 11.5px;">
                <span>
                    @if($dateFilter === 'today')
                        Today's recovered revenue
                    @elseif($dateFilter === 'week')
                        Recovered this week
                    @elseif($dateFilter === 'custom')
                        Recovered in custom range
                    @else
                        All-time recovered revenue
                    @endif
                </span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Bookings</div>
            <div class="stat-value">{{ $activeBookings }}</div>
            <div class="stat-change" style="color: var(--text-muted); font-size: 11.5px;">
                <span>
                    @if($dateFilter === 'today')
                        Active bookings created today
                    @elseif($dateFilter === 'week')
                        Active bookings created this week
                    @elseif($dateFilter === 'custom')
                        Active bookings in custom range
                    @else
                        Current total active bookings
                    @endif
                </span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Today's Scheduled Reminders</div>
            <div class="stat-value">{{ $todayRemindersCount }}</div>
            <div class="stat-change @if($todayRemindersCount > 0) change-up @else change-down @endif">
                <span>{{ $todayRemindersCount > 0 ? 'Pending send today' : 'No alerts scheduled today' }}</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Alert Subscribers</div>
            <div class="stat-value">{{ number_format($alertSubscribersCount) }}</div>
            <div class="stat-change" style="color: var(--text-muted); font-size: 11.5px;">
                <span>
                    @if($dateFilter === 'today')
                        Subscribers joined today
                    @elseif($dateFilter === 'week')
                        Subscribers joined this week
                    @elseif($dateFilter === 'custom')
                        Subscribers joined in custom range
                    @else
                        Total alert subscribers
                    @endif
                </span>
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
                <h3>Recent Bookings</h3>
                <div class="recent-bookings-list">
                    @if($bookings->isEmpty())
                        <!-- Default Mockups if DB is clean -->
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
                                <span class="status-pill deposit_paid">Deposit Paid</span>
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
                                <span class="status-pill deposit_paid">Deposit Paid</span>
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
                                <span class="status-pill pending">Pending</span>
                            </div>
                        </div>
                    @else
                        @foreach($bookings->take(5) as $booking)
                            @php
                                $displayName = ($booking->customer_name && strtolower($booking->customer_name) !== 'n/a' && strtolower($booking->customer_name) !== 'null') ? $booking->customer_name : $booking->email;
                            @endphp
                            <div class="booking-item">
                                <div class="user-avatar-info">
                                    <div class="avatar-circle">{{ strtoupper(substr($displayName, 0, 2)) }}</div>
                                    <div class="user-meta">
                                        <h4>{{ $displayName }}</h4>
                                        <p>{{ $booking->product_title }}</p>
                                    </div>
                                </div>
                                <div class="booking-status-price">
                                    <span class="price-value">${{ number_format($booking->deposit_amount, 2) }}</span>
                                    <span class="status-pill {{ $booking->status }}">{{ $booking->status === 'completed' ? 'Full Paid' : str_replace('_', ' ', $booking->status) }}</span>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- Booking Status Breakdown Donut Chart -->
            <div class="panel-card">
                <h3>Booking Status Breakdown</h3>
                <div class="donut-chart-container" style="position: relative; height: 180px; display: flex; justify-content: center; align-items: center; margin-bottom: 16px;">
                    <canvas id="statusDonutChart" style="max-height: 160px; max-width: 160px;"></canvas>
                    <div class="donut-center-text" style="position: absolute; text-align: center; pointer-events: none; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: -2px;">Total</span>
                        <span style="font-size: 24px; font-weight: 700; color: var(--text-main); line-height: 1;">{{ array_sum($statusCounts) }}</span>
                    </div>
                </div>
                <div class="donut-legend" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 12px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background-color: #6d7175; display: inline-block;"></span>
                        <span style="color: var(--text-muted);">Pending:</span>
                        <strong style="color: var(--text-main);">{{ $statusCounts['pending'] }}</strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background-color: #005ea2; display: inline-block;"></span>
                        <span style="color: var(--text-muted);">Deposit Paid:</span>
                        <strong style="color: var(--text-main);">{{ $statusCounts['deposit_paid'] }}</strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background-color: #108043; display: inline-block;"></span>
                        <span style="color: var(--text-muted);">Fully Paid:</span>
                        <strong style="color: var(--text-main);">{{ $statusCounts['completed'] }}</strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background-color: #d82c0d; display: inline-block;"></span>
                        <span style="color: var(--text-muted);">Expired:</span>
                        <strong style="color: var(--text-main);">{{ $statusCounts['expired'] }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Wishes and Recovered Charts -->
        <div class="dashboard-main-grid">
            <!-- Most Wished Products -->
            <div class="panel-card">
                <h3>Most Wished Products</h3>
                <div class="wishes-container">
                    @if(empty($wishes))
                        <p style="color: var(--text-muted); font-size: 13.5px; text-align: center; padding: 40px 0;">No wishes recorded in this period.</p>
                    @else
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
                    @endif
                </div>
            </div>

            <!-- Expiring Soon Panel -->
            <div class="panel-card" style="display: flex; flex-direction: column;">
                <h3>Expiring Soon <span style="font-size: 12px; font-weight: normal; color: var(--text-muted); background: #f1f2f4; padding: 2px 8px; border-radius: 12px; margin-left: 8px;">Next 7 Days</span></h3>
                <div class="expiring-soon-list" style="flex: 1; display: flex; flex-direction: column; gap: 16px; overflow-y: auto; max-height: 280px; padding-right: 4px;">
                    
                    @if($expiringToday->isEmpty() && $expiringTomorrow->isEmpty() && $expiringThisWeek->isEmpty())
                        <div style="text-align: center; color: var(--text-muted); font-size: 13.5px; padding: 40px 10px;">
                            No upcoming expirations in the next 7 days.
                        </div>
                    @else
                        <!-- Today Group -->
                        @if($expiringToday->isNotEmpty())
                            <div class="expiry-group" style="border-left: 4px solid var(--danger-color); padding-left: 12px;">
                                <div style="font-size: 11px; font-weight: 700; color: var(--danger-color); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; display: flex; align-items: center; gap: 4px;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background-color: var(--danger-color); display: inline-block; animation: pulse 1.5s infinite;"></span>
                                    Today
                                </div>
                                @foreach($expiringToday as $booking)
                                    @php
                                        $displayName = ($booking->customer_name && strtolower($booking->customer_name) !== 'n/a' && strtolower($booking->customer_name) !== 'null') ? $booking->customer_name : $booking->email;
                                    @endphp
                                    <div class="expiry-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px dashed var(--border-color);">
                                        <div>
                                            <div style="font-size: 13.5px; font-weight: 600; color: var(--text-main);">{{ $displayName }}</div>
                                            <div style="font-size: 11.5px; color: var(--text-muted);">{{ $booking->product_title }}</div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <span style="font-size: 12px; color: var(--danger-color); font-weight: 600;">{{ \Carbon\Carbon::parse($booking->expires_at)->format('M j') }}</span>
                                            <form action="{{ route('bookings.send_reminder', array_merge(['id' => $booking->id], request()->query())) }}" method="POST" style="margin:0;">
                                                @csrf
                                                <button type="submit" class="btn-action-secondary" style="padding: 4px 8px; font-size: 11.5px;">
                                                    Send Reminder
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Tomorrow Group -->
                        @if($expiringTomorrow->isNotEmpty())
                            <div class="expiry-group" style="border-left: 4px solid var(--warning-color); padding-left: 12px;">
                                <div style="font-size: 11px; font-weight: 700; color: var(--warning-color); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
                                    Tomorrow
                                </div>
                                @foreach($expiringTomorrow as $booking)
                                    @php
                                        $displayName = ($booking->customer_name && strtolower($booking->customer_name) !== 'n/a' && strtolower($booking->customer_name) !== 'null') ? $booking->customer_name : $booking->email;
                                    @endphp
                                    <div class="expiry-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px dashed var(--border-color);">
                                        <div>
                                            <div style="font-size: 13.5px; font-weight: 600; color: var(--text-main);">{{ $displayName }}</div>
                                            <div style="font-size: 11.5px; color: var(--text-muted);">{{ $booking->product_title }}</div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <span style="font-size: 12px; color: var(--warning-color); font-weight: 600;">{{ \Carbon\Carbon::parse($booking->expires_at)->format('M j') }}</span>
                                            <form action="{{ route('bookings.send_reminder', array_merge(['id' => $booking->id], request()->query())) }}" method="POST" style="margin:0;">
                                                @csrf
                                                <button type="submit" class="btn-action-secondary" style="padding: 4px 8px; font-size: 11.5px;">
                                                    Send Reminder
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- This Week Group -->
                        @if($expiringThisWeek->isNotEmpty())
                            <div class="expiry-group" style="border-left: 4px solid var(--accent-blue); padding-left: 12px;">
                                <div style="font-size: 11px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
                                    This Week
                                </div>
                                @foreach($expiringThisWeek as $booking)
                                    @php
                                        $displayName = ($booking->customer_name && strtolower($booking->customer_name) !== 'n/a' && strtolower($booking->customer_name) !== 'null') ? $booking->customer_name : $booking->email;
                                    @endphp
                                    <div class="expiry-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px dashed var(--border-color);">
                                        <div>
                                            <div style="font-size: 13.5px; font-weight: 600; color: var(--text-main);">{{ $displayName }}</div>
                                            <div style="font-size: 11.5px; color: var(--text-muted);">{{ $booking->product_title }}</div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <span style="font-size: 12px; color: var(--text-muted);">{{ \Carbon\Carbon::parse($booking->expires_at)->format('M j') }}</span>
                                            <form action="{{ route('bookings.send_reminder', array_merge(['id' => $booking->id], request()->query())) }}" method="POST" style="margin:0;">
                                                @csrf
                                                <button type="submit" class="btn-action-secondary" style="padding: 4px 8px; font-size: 11.5px;">
                                                    Send Reminder
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif

                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Bookings List (Required fields & actions) -->
    <div id="tab-bookings-list" class="tab-content" style="display: none;">
        <div class="panel-card-table">
            <h3>Bookings & Deposit Holds</h3>
            @if($bookings->isEmpty())
                <p style="color: var(--text-muted); text-align: center; padding: 20px 0;">No bookings created yet.</p>
            @else
                <div class="table-control-bar">
                    <div class="search-input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" id="search-bookings" placeholder="Search bookings (name, email, product)..." oninput="filterBookings()">
                    </div>
                    <select id="filter-bookings-status" class="filter-select" onchange="filterBookings()">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="deposit_paid">Deposit Paid</option>
                        <option value="completed">Full Paid</option>
                        <option value="expired">Expired</option>
                    </select>
                    <select id="sort-bookings" class="filter-select" onchange="filterBookings()">
                        <option value="date_desc">Newest First</option>
                        <option value="date_asc">Oldest First</option>
                        <option value="balance_desc">Remaining Balance: High to Low</option>
                        <option value="balance_asc">Remaining Balance: Low to High</option>
                    </select>
                    <button class="export-btn" onclick="exportBookingsToCSV()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>Export CSV</span>
                    </button>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Customer Info</th>
                                <th>Product Details</th>
                                <th>Financials (Deposit + Remaining)</th>
                                <th>Status</th>
                                <th>Expiry Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bookings as $booking)
                                @php
                                    $searchText = strtolower(($booking->customer_name ?? '') . ' ' . $booking->email . ' ' . $booking->product_title);
                                    $createdAtTimestamp = $booking->created_at ? $booking->created_at->timestamp : 0;
                                @endphp
                                <tr data-search-text="{{ $searchText }}" data-status="{{ $booking->status }}" data-created-at="{{ $createdAtTimestamp }}" data-balance="{{ $booking->remaining_balance }}">
                                    <td>
                                        @if($booking->customer_name && strtolower($booking->customer_name) !== 'n/a' && strtolower($booking->customer_name) !== 'null' && strtolower($booking->customer_name) !== strtolower($booking->email))
                                            <strong>{{ $booking->customer_name }}</strong><br>
                                            <span style="font-size: 12px; color: var(--text-muted);">{{ $booking->email }}</span>
                                        @else
                                            <span style="font-size: 13.5px; font-weight: 500;">{{ $booking->email }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $booking->product_title }}</strong>
                                    </td>
                                    <td>
                                        <div style="font-size: 13.5px;">
                                            Deposit paid: <span style="font-weight:600; color:var(--secondary-color);">${{ number_format($booking->deposit_amount, 2) }}</span><br>
                                            Remaining balance: <span style="font-weight:600; color:var(--accent-blue);">${{ number_format($booking->remaining_balance, 2) }}</span><br>
                                            <span style="font-size:11.5px; color: var(--text-muted);">Total: ${{ number_format($booking->product_price, 2) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $booking->status }}">{{ $booking->status === 'completed' ? 'Full Paid' : str_replace('_', ' ', $booking->status) }}</span>
                                    </td>
                                    <td>
                                        @if($booking->expires_at)
                                            @php
                                                $expiresAt = \Carbon\Carbon::parse($booking->expires_at);
                                                $daysLeft = \Carbon\Carbon::now()->diffInDays($expiresAt, false);
                                                $isUrgent = ($daysLeft >= 0 && $daysLeft < 3) && !in_array($booking->status, ['completed', 'expired']);
                                            @endphp
                                            <span style="@if($isUrgent) color: var(--danger-color); font-weight:700; @endif">
                                                {{ $expiresAt->format('M j, Y') }}
                                                @if($isUrgent)
                                                    <br><small>🚨 ({{ round($daysLeft) }} days left)</small>
                                                @endif
                                            </span>
                                        @else
                                            <span style="color: var(--text-muted);">No expiry</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="actions-cell">

                                            @if($booking->status !== 'completed' && $booking->status !== 'expired')
                                                <form action="{{ route('bookings.send_reminder', array_merge(['id' => $booking->id], request()->query())) }}" method="POST" style="margin:0;">
                                                    @csrf
                                                    <button type="submit" class="btn-action-secondary">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                                        Send Reminder
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            <tr id="bookings-no-results" style="display: none;">
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
                                    No bookings match your search/filter criteria.
                                </td>
                            </tr>
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
            @if($reminders->isEmpty())
                <p style="color: var(--text-muted); text-align: center; padding: 20px 0;">No customer reminders scheduled yet.</p>
            @else
                <div class="table-control-bar">
                    <div class="search-input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" id="search-reminders" placeholder="Search reminders (email, product)..." oninput="filterReminders()">
                    </div>
                    <select id="filter-reminders-status" class="filter-select" onchange="filterReminders()">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="sent">Sent</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button class="export-btn" onclick="exportRemindersToCSV()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>Export CSV</span>
                    </button>
                </div>
                <div class="table-responsive">
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
                                @php
                                    $searchText = strtolower($reminder->email . ' ' . $reminder->product_title);
                                @endphp
                                <tr data-search-text="{{ $searchText }}" data-status="{{ $reminder->status }}">
                                    <td>{{ $reminder->product_title }}</td>
                                    <td>{{ $reminder->email }}</td>
                                    <td class="local-datetime" data-utc="{{ $reminder->scheduled_at->toIso8601String() }}">{{ $reminder->scheduled_at->format('M j, Y g:i a') }}</td>
                                    <td><span class="status-pill {{ $reminder->status }}">{{ $reminder->status }}</span></td>
                                    <td class="local-datetime" data-utc="{{ $reminder->sent_at ? $reminder->sent_at->toIso8601String() : '' }}">{{ $reminder->sent_at ? $reminder->sent_at->format('M j, Y g:i a') : '-' }}</td>
                                </tr>
                            @endforeach
                            <tr id="reminders-no-results" style="display: none;">
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
                                    No reminders match your search/filter criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Tab 4: Price Alerts -->
    <div id="tab-subscribers-list" class="tab-content" style="display: none;">
        <div class="panel-card-table">
            <h3>Price Drop Subscribers</h3>
            @if($subscribers->isEmpty())
                <p style="color: var(--text-muted); text-align: center; padding: 20px 0;">No price drop subscriptions active.</p>
            @else
                <div class="table-control-bar">
                    <div class="search-input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" id="search-subscribers" placeholder="Search subscribers (email, product)..." oninput="filterSubscribers()">
                    </div>
                    <select id="filter-subscribers-status" class="filter-select" onchange="filterSubscribers()">
                        <option value="all">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="sent">Sent</option>
                    </select>
                    <button class="export-btn" onclick="exportSubscribersToCSV()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        <span>Export CSV</span>
                    </button>
                </div>
                <div class="table-responsive">
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
                                @php
                                    $searchText = strtolower($subscriber->email . ' ' . $subscriber->product_title);
                                @endphp
                                <tr data-search-text="{{ $searchText }}" data-status="{{ $subscriber->status }}">
                                    <td>{{ $subscriber->product_title }}</td>
                                    <td>{{ $subscriber->email }}</td>
                                    <td>${{ number_format((float)$subscriber->product_price, 2) }}</td>
                                    <td><span class="status-pill {{ $subscriber->status }}">{{ $subscriber->status }}</span></td>
                                    <td>{{ $subscriber->created_at->format('M j, Y') }}</td>
                                </tr>
                            @endforeach
                            <tr id="subscribers-no-results" style="display: none;">
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
                                    No subscribers match your search/filter criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
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
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="hold_duration_days">Reservation Hold Duration (Days)</label>
                        <input type="number" id="hold_duration_days" name="hold_duration_days" value="{{ $settings->hold_duration_days ?? 14 }}" min="1" max="365" required>
                    </div>
                    <div class="form-group">
                        <!-- Spacer for 2-column layout grid -->
                    </div>
                </div>
                <div class="form-group" style="margin-top: 16px;">
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Enabled Storefront Widget Options</label>
                    <div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap; margin-top: 8px;">
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13.5px; font-weight: 500; color: var(--text-main);">
                            <input type="checkbox" name="show_deposit" value="1" {{ ($settings->show_deposit ?? true) ? 'checked' : '' }} style="width: 16px; height: 16px; accent-color: var(--primary-color);">
                            Book Now (Deposit Hold)
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13.5px; font-weight: 500; color: var(--text-main);">
                            <input type="checkbox" name="show_reminders" value="1" {{ ($settings->show_reminders ?? true) ? 'checked' : '' }} style="width: 16px; height: 16px; accent-color: var(--primary-color);">
                            Remind Me Later
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13.5px; font-weight: 500; color: var(--text-main);">
                            <input type="checkbox" name="show_alerts" value="1" {{ ($settings->show_alerts ?? true) ? 'checked' : '' }} style="width: 16px; height: 16px; accent-color: var(--primary-color);">
                            Discount Alerts
                        </label>
                    </div>
                </div>
            </div>

            <!-- Card 2: Email Templates -->
            <div class="panel-card">
                <h3>Email Templates & Sender Display</h3>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="sender_display_name">Sender Display Name</label>
                    <input type="text" id="sender_display_name" name="sender_display_name" value="{{ $settings->sender_display_name ?? ($settings->shop->name ?? 'Your Store') . ' via BuyLater' }}" required>
                </div>
                <div class="form-group">
                    <label for="reminder_email_subject">Reminder Email Subject</label>
                    <input type="text" id="reminder_email_subject" name="reminder_email_subject" value="{{ $settings->reminder_email_subject }}" required>
                </div>
                <div class="form-group">
                    <label for="reminder_email_template">Reminder Email HTMLTemplate</label>
                    <textarea id="reminder_email_template" name="reminder_email_template" rows="8">{{ $settings->reminder_email_template }}</textarea>
                </div>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 24px 0;">
                <div class="form-group">
                    <label for="discount_email_subject">Price Drop Email Subject</label>
                    <input type="text" id="discount_email_subject" name="discount_email_subject" value="{{ $settings->discount_email_subject }}" required>
                </div>
                <div class="form-group">
                    <label for="discount_email_template">Price Drop Email HTML Template</label>
                    <textarea id="discount_email_template" name="discount_email_template" rows="8">{{ $settings->discount_email_template }}</textarea>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn-save">Save All Settings</button>
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
            const tabButton = Array.from(document.querySelectorAll('.tab-button')).find(btn => {
                const onclick = btn.getAttribute('onclick') || '';
                return onclick.includes(tabId);
            });
            if (tabButton) {
                tabButton.classList.add('active');
            }
        }
    }

// Apply date filter by updating query parameters
function applyDateFilter(filter, startDate = null, endDate = null) {
    const params = new URLSearchParams(window.location.search);
    params.set('date_filter', filter);
    if (filter === 'custom') {
        if (startDate) params.set('start_date', startDate);
        if (endDate) params.set('end_date', endDate);
    } else {
        params.delete('start_date');
        params.delete('end_date');
    }
    window.location.search = params.toString();
}

// Client-side search, filtering and sorting logic
function filterBookings() {
    const searchVal = document.getElementById('search-bookings').value.toLowerCase().trim();
    const statusVal = document.getElementById('filter-bookings-status').value;
    const sortVal = document.getElementById('sort-bookings').value;
    const tbody = document.querySelector('#tab-bookings-list tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(#bookings-no-results)'));
    const noResultsRow = document.getElementById('bookings-no-results');

    let visibleCount = 0;

    rows.forEach(row => {
        const searchText = row.getAttribute('data-search-text') || '';
        const status = row.getAttribute('data-status') || '';
        
        const matchesSearch = searchText.includes(searchVal);
        const matchesStatus = statusVal === 'all' || status === statusVal;

        if (matchesSearch && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (visibleCount === 0) {
        noResultsRow.style.display = '';
    } else {
        noResultsRow.style.display = 'none';
        
        // Handle sorting if visible
        if (sortVal) {
            rows.sort((a, b) => {
                if (sortVal === 'date_desc') {
                    return parseInt(b.getAttribute('data-created-at')) - parseInt(a.getAttribute('data-created-at'));
                } else if (sortVal === 'date_asc') {
                    return parseInt(a.getAttribute('data-created-at')) - parseInt(b.getAttribute('data-created-at'));
                } else if (sortVal === 'balance_desc') {
                    return parseFloat(b.getAttribute('data-balance')) - parseFloat(a.getAttribute('data-balance'));
                } else if (sortVal === 'balance_asc') {
                    return parseFloat(a.getAttribute('data-balance')) - parseFloat(b.getAttribute('data-balance'));
                }
                return 0;
            });
            // Re-append sorted rows to update the DOM order
            rows.forEach(row => tbody.appendChild(row));
        }
    }
}

function filterReminders() {
    const searchVal = document.getElementById('search-reminders').value.toLowerCase().trim();
    const statusVal = document.getElementById('filter-reminders-status').value;
    const tbody = document.querySelector('#tab-reminders-list tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(#reminders-no-results)'));
    const noResultsRow = document.getElementById('reminders-no-results');

    let visibleCount = 0;

    rows.forEach(row => {
        const searchText = row.getAttribute('data-search-text') || '';
        const status = row.getAttribute('data-status') || '';
        
        const matchesSearch = searchText.includes(searchVal);
        const matchesStatus = statusVal === 'all' || status === statusVal;

        if (matchesSearch && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (visibleCount === 0) {
        noResultsRow.style.display = '';
    } else {
        noResultsRow.style.display = 'none';
    }
}

function filterSubscribers() {
    const searchVal = document.getElementById('search-subscribers').value.toLowerCase().trim();
    const statusVal = document.getElementById('filter-subscribers-status').value;
    const tbody = document.querySelector('#tab-subscribers-list tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(#subscribers-no-results)'));
    const noResultsRow = document.getElementById('subscribers-no-results');

    let visibleCount = 0;

    rows.forEach(row => {
        const searchText = row.getAttribute('data-search-text') || '';
        const status = row.getAttribute('data-status') || '';
        
        const matchesSearch = searchText.includes(searchVal);
        const matchesStatus = statusVal === 'all' || status === statusVal;

        if (matchesSearch && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (visibleCount === 0) {
        noResultsRow.style.display = '';
    } else {
        noResultsRow.style.display = 'none';
    }
}

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        let tabParam = urlParams.get('tab');
        if (!tabParam && window.location.hash) {
            tabParam = window.location.hash.replace('#', '');
        }
        if (tabParam) {
            switchTab(null, 'tab-' + tabParam);
        }

        // Initialize status breakdown chart
        const statusTotal = {{ array_sum($statusCounts) }};
        const ctx = document.getElementById('statusDonutChart').getContext('2d');
        
        let chartData = {
            labels: ['Pending', 'Deposit Paid', 'Fully Paid', 'Expired'],
            datasets: [{
                data: [
                    {{ $statusCounts['pending'] }},
                    {{ $statusCounts['deposit_paid'] }},
                    {{ $statusCounts['completed'] }},
                    {{ $statusCounts['expired'] }}
                ],
                backgroundColor: ['#6d7175', '#005ea2', '#108043', '#d82c0d'],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 4
            }]
        };

        if (statusTotal === 0) {
            chartData = {
                labels: ['No Bookings'],
                datasets: [{
                    data: [1],
                    backgroundColor: ['#e4e5e7'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 0
                }]
            };
        }

        new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: statusTotal > 0,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    });

    function downloadCSV(filename, headers, rows) {
        const escapeCSVVal = (val) => {
            if (val === null || val === undefined) return '';
            let stringVal = String(val);
            stringVal = stringVal.replace(/"/g, '""');
            if (stringVal.search(/("|,|\n)/g) >= 0) {
                stringVal = `"${stringVal}"`;
            }
            return stringVal;
        };
        
        const headerRow = headers.map(escapeCSVVal).join(",");
        const csvContent = [headerRow, ...rows.map(row => row.map(escapeCSVVal).join(","))].join("\n");
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    function exportBookingsToCSV() {
        const data = @json($bookings);
        if (!data || data.length === 0) {
            alert("No bookings to export.");
            return;
        }
        
        const headers = ["ID", "Customer Name", "Email", "Product Title", "Product Price", "Deposit Amount", "Remaining Balance", "Status", "Expiry Date", "Created At"];
        const rows = data.map(b => [
            b.id,
            b.customer_name || '',
            b.email,
            b.product_title,
            b.product_price,
            b.deposit_amount,
            b.remaining_balance,
            b.status,
            b.expires_at || 'No expiry',
            b.created_at
        ]);
        
        downloadCSV("bookings_export.csv", headers, rows);
    }

    function exportRemindersToCSV() {
        const data = @json($reminders);
        if (!data || data.length === 0) {
            alert("No reminders to export.");
            return;
        }
        
        const headers = ["ID", "Product Title", "Customer Email", "Scheduled At", "Status", "Sent At", "Created At"];
        const rows = data.map(r => [
            r.id,
            r.product_title,
            r.email,
            r.scheduled_at,
            r.status,
            r.sent_at || '-',
            r.created_at
        ]);
        
        downloadCSV("reminders_export.csv", headers, rows);
    }

    function exportSubscribersToCSV() {
        const data = @json($subscribers);
        if (!data || data.length === 0) {
            alert("No price drop subscribers to export.");
            return;
        }
        
        const headers = ["ID", "Product Title", "Email", "Original Price", "Status", "Subscribed Date"];
        const rows = data.map(s => [
            s.id,
            s.product_title,
            s.email,
            s.product_price,
            s.status,
            s.created_at
        ]);
        
        downloadCSV("subscribers_export.csv", headers, rows);
    }

    // Convert all UTC timestamps to local browser timezone on load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.local-datetime').forEach(el => {
            const utcDateStr = el.getAttribute('data-utc');
            if (utcDateStr) {
                const date = new Date(utcDateStr);
                if (!isNaN(date.getTime())) {
                    // Formats using user's system locale, e.g. "Jul 8, 2026, 8:55 PM" or 24h format based on OS
                    el.textContent = date.toLocaleString(undefined, {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit'
                    });
                } else {
                    el.textContent = '-';
                }
            }
        });
    });

    // Intercept standard HTML POST form submissions to retrieve and inject a fresh Shopify session token
    document.addEventListener('submit', async function (e) {
        const form = e.target;
        if (form.method && form.method.toLowerCase() === 'post' && !form.dataset.tokenInjected) {
            e.preventDefault();
            try {
                if (window.shopify && typeof window.shopify.idToken === 'function') {
                    const token = await window.shopify.idToken();
                    let tokenInput = form.querySelector('input[name="token"]');
                    if (!tokenInput) {
                        tokenInput = document.createElement('input');
                        tokenInput.type = 'hidden';
                        tokenInput.name = 'token';
                        form.appendChild(tokenInput);
                    }
                    tokenInput.value = token;
                }
                form.dataset.tokenInjected = 'true';
                form.submit();
            } catch (err) {
                console.error('Failed to retrieve fresh Shopify session token:', err);
                form.dataset.tokenInjected = 'true';
                form.submit();
            }
        }
    });
</script>
@endsection
