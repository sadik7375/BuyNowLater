@extends('shopify-app::layouts.default')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
<style>
    :root {
        --bg-main: #f6f6f7;
        --bg-card: #ffffff;
        --text-main: #202223;
        --text-muted: #6d7175;
        --border-color: #e1e3e5;
        --primary-color: #1a1a1a; /* Shopify Primary Dark Grey */
        --primary-hover: #2f3337;
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
        padding: 0;
    }

    .app-layout {
        display: flex;
        min-height: 100vh;
    }

    /* ── LEFT SIDEBAR ── */
    .sidebar {
        width: 220px;
        flex-shrink: 0;
        background: #1a1d23;
        display: flex;
        flex-direction: column;
        padding: 0;
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        overflow-y: auto;
        z-index: 100;
    }

    .sidebar-brand {
        padding: 20px 20px 16px;
        border-bottom: 1px solid rgba(255,255,255,0.07);
    }

    .sidebar-brand h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: -0.3px;
    }

    .sidebar-brand h2 span {
        color: #4ade9b;
    }

    .sidebar-brand small {
        font-size: 11px;
        color: rgba(255,255,255,0.35);
        display: block;
        margin-top: 3px;
    }

    .sidebar-section-label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: rgba(255,255,255,0.3);
        padding: 18px 20px 6px;
    }

    .sidebar-nav {
        flex: 1;
        padding: 8px 12px;
    }

    .sidebar-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 10px 12px;
        border: none;
        background: transparent;
        color: rgba(255,255,255,0.6);
        font-family: 'Outfit', sans-serif;
        font-size: 13.5px;
        font-weight: 500;
        border-radius: 8px;
        cursor: pointer;
        text-align: left;
        transition: all 0.18s ease;
        margin-bottom: 2px;
    }

    .sidebar-btn:hover {
        background: rgba(255,255,255,0.07);
        color: #ffffff;
    }

    .sidebar-btn.active {
        background: rgba(74, 222, 155, 0.15);
        color: #4ade9b;
        font-weight: 600;
    }

    .sidebar-btn .icon {
        font-size: 16px;
        width: 20px;
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar-divider {
        border: none;
        border-top: 1px solid rgba(255,255,255,0.07);
        margin: 10px 12px;
    }

    /* ── MAIN CONTENT ── */
    .main-content {
        margin-left: 220px;
        flex: 1;
        padding: 24px;
        min-width: 0;
    }

    .dashboard-container {
        max-width: 1180px;
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

    /* Quick stats cards (Shopify Polaris style) */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background-color: #ffffff;
        border: 1px solid #e1e3e5;
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0px 1px 0px rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }

    .stat-info {
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .stat-label {
        font-size: 13px;
        color: #202223;
        margin-bottom: 8px;
        font-weight: 450;
        display: inline-block;
        border-bottom: 1px dashed #e1e3e5;
        cursor: help;
        width: fit-content;
    }

    .stat-value {
        font-size: 20px;
        font-weight: 600;
        color: #202223;
        margin-bottom: 4px;
        line-height: 1.2;
    }

    .stat-change {
        font-size: 11.5px;
        font-weight: 400;
        color: #6d7175;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .change-up { color: var(--secondary-color); font-weight: 500; }
    .change-down { color: var(--danger-color); font-weight: 500; }

    .stat-visual {
        flex-shrink: 0;
        margin-left: 12px;
        margin-bottom: 4px;
    }

    /* Tabs navigation — kept for JS compatibility, hidden visually */
    .dashboard-tabs { display: none; }
    .tab-button { display: none; }

    /* Main Grid Panels */
    .dashboard-main-grid {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    /* Card Panels (Shopify Polaris style) */
    .panel-card {
        background-color: #ffffff;
        border: 1px solid #e1e3e5;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0px 1px 0px rgba(0, 0, 0, 0.05);
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

    .btn-action-secondary.loading {
        pointer-events: none;
        opacity: 0.8;
    }

    .spinner {
        display: inline-block;
        width: 12px;
        height: 12px;
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-radius: 50%;
        border-top-color: var(--primary-color);
        animation: spin 1s linear infinite;
        margin-right: 6px;
        vertical-align: middle;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .btn-action-secondary.success-state {
        background-color: #e3fbeb !important;
        color: var(--secondary-color) !important;
        border-color: rgba(48, 209, 88, 0.3) !important;
        pointer-events: none;
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
        border-radius: 8px;
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

    /* Date Picker Popover styling (Shopify Polaris style) */
    .date-picker-wrapper {
        position: relative;
        display: inline-block;
    }

    .date-picker-activator {
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-main);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        transition: all 0.15s ease;
        box-shadow: 0px 1px 0px rgba(0, 0, 0, 0.05);
    }

    .date-picker-activator:hover {
        background: #f6f6f7;
        border-color: #c9cccf;
    }

    .date-picker-popover {
        position: absolute;
        right: 0;
        top: calc(100% + 6px);
        background: #ffffff;
        border: 1px solid #e1e3e5;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
        z-index: 1100;
        display: none; /* Controlled by JS: flex/none */
        flex-direction: column;
        width: auto;
        min-width: 320px;
    }

    .popover-content {
        padding: 12px;
        overflow: hidden;
    }

    .popover-content s-date-picker {
        width: 100%;
        display: block;
    }

    .popover-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding: 12px 16px;
        border-top: 1px solid #e1e3e5;
        background: #ffffff;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
    }

    .popover-btn-cancel {
        background: #ffffff;
        border: 1px solid #e1e3e5;
        color: var(--text-main);
        padding: 6px 12px;
        font-size: 12.5px;
        font-weight: 500;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .popover-btn-cancel:hover {
        background: #f6f6f7;
    }

    .popover-btn-apply {
        background: var(--primary-color);
        border: 1px solid var(--primary-color);
        color: #ffffff;
        padding: 6px 12px;
        font-size: 12.5px;
        font-weight: 500;
        border-radius: 6px;
        cursor: pointer;
        transition: opacity 0.15s ease;
    }

    .popover-btn-apply:hover {
        opacity: 0.95;
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

    /* Subscription Status Banner */
    .subscription-banner {
        background: linear-gradient(135deg, #102a43 0%, #243b53 100%);
        color: #ffffff;
        border-radius: 12px;
        padding: 20px 24px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 15px rgba(16, 42, 67, 0.15);
        position: relative;
        overflow: hidden;
    }

    .subscription-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .sub-info-col h3 {
        margin: 0 0 8px 0;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #ffffff;
    }

    .plan-badge {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 4px 8px;
        border-radius: 20px;
        letter-spacing: 0.05em;
    }

    .plan-badge.free {
        background-color: #f1f2f4;
        color: #202223;
    }

    .plan-badge.pro {
        background-color: #e3fbeb;
        color: #108043;
        box-shadow: 0 0 10px rgba(16, 128, 67, 0.2);
    }

    .sub-info-col p {
        margin: 0;
        font-size: 13.5px;
        color: #9fb3c8;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .sub-usage-col {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 240px;
    }

    .usage-label {
        font-size: 12px;
        font-weight: 600;
        color: #9fb3c8;
        display: flex;
        justify-content: space-between;
    }

    .usage-progress-bar {
        height: 8px;
        background-color: rgba(255, 255, 255, 0.15);
        border-radius: 4px;
        overflow: hidden;
    }

    .usage-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #38bec9 0%, #4facfe 100%);
        border-radius: 4px;
        transition: width 0.4s ease;
    }

    .sub-actions-col {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .btn-upgrade {
        background: linear-gradient(135deg, #303030 0%, #1a1a1a 100%);
        color: #ffffff;
        border: none;
        padding: 10px 20px;
        font-size: 13.5px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-upgrade:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 128, 96, 0.3);
    }

    .btn-downgrade {
        background: transparent;
        color: #9fb3c8;
        border: 1px solid rgba(159, 179, 200, 0.3);
        padding: 9px 18px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
    }

    /* How it works & Benefits guide pages styling */
    .guide-header {
        margin-bottom: 28px;
    }
    .guide-header h2 {
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 6px 0;
        color: var(--text-main);
    }
    .guide-header p {
        font-size: 14px;
        color: var(--text-muted);
        margin: 0;
    }
    .guide-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    .guide-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    .guide-card-premium {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.015);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .guide-card-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
        border-color: #c9ccd0;
    }
    .guide-card-badge {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: rgba(0, 128, 96, 0.08);
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 16px;
    }
    .guide-card-premium h4 {
        margin: 0 0 10px 0;
        font-size: 15px;
        font-weight: 600;
        color: var(--text-main);
    }
    .guide-card-premium p {
        margin: 0;
        font-size: 13.5px;
        line-height: 1.5;
        color: var(--text-muted);
    }
    .guide-timeline {
        display: flex;
        flex-direction: column;
        gap: 16px;
        position: relative;
        padding-left: 20px;
    }
    .guide-timeline::before {
        content: '';
        position: absolute;
        left: 6px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: var(--border-color);
    }
    .guide-timeline-item {
        position: relative;
        padding-left: 20px;
    }
    .guide-timeline-item::before {
        content: '';
        position: absolute;
        left: -19px;
        top: 4px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--primary-color);
        border: 2px solid var(--bg-card);
    }
    .guide-timeline-item h5 {
        margin: 0 0 4px 0;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-main);
    }
    .guide-timeline-item p {
        margin: 0;
        font-size: 13px;
        color: var(--text-muted);
        line-height: 1.45;
    }

    /* --- Details Modal --- */
    .details-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.4);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(4px);
        transition: opacity 0.2s ease;
    }
    .details-modal.show {
        display: flex;
    }
    .details-modal-content {
        background-color: #ffffff;
        border-radius: 12px;
        width: 100%;
        max-width: 550px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        border: 1px solid var(--border-color, #e2e8f0);
        overflow: hidden;
        animation: modalFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes modalFadeIn {
        from { transform: scale(0.95); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    .details-modal-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color, #e2e8f0);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f8fafc;
    }
    .details-modal-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: var(--text-main, #0f172a);
    }
    .details-modal-close {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        color: var(--text-muted, #64748b);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4px;
        border-radius: 4px;
        transition: background 0.15s;
    }
    .details-modal-close:hover {
        background: rgba(0, 0, 0, 0.05);
    }
    .details-modal-body {
        padding: 20px;
        font-size: 13.5px;
        color: var(--text-main, #0f172a);
        max-height: 450px;
        overflow-y: auto;
    }
    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }
    .details-section-title {
        grid-column: span 2;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted, #64748b);
        border-bottom: 1px solid var(--border-color, #e2e8f0);
        padding-bottom: 6px;
        margin-top: 10px;
    }
    .details-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .details-item.full-width {
        grid-column: span 2;
    }
    .details-item label {
        font-size: 11.5px;
        font-weight: 600;
        color: var(--text-muted, #64748b);
    }
    .details-item span {
        font-weight: 500;
    }
    .details-badge-wrapper {
        display: inline-flex;
    }
    .shopify-link {
        color: var(--primary-color, #008060);
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .shopify-link:hover {
        text-decoration: underline;
    }

    /* --- Pricing Plan Layout --- */
    .pricing-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
        margin-top: 20px;
    }
    .pricing-card {
        background: #ffffff;
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 12px;
        padding: 30px;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: all 0.2s ease;
    }
    .pricing-card.popular {
        border-color: var(--primary-color, #008060);
        box-shadow: 0 10px 15px -3px rgba(0, 128, 96, 0.1), 0 4px 6px -2px rgba(0, 128, 96, 0.05);
    }
    .pricing-card.popular::before {
        content: 'RECOMMENDED';
        position: absolute;
        top: -12px;
        right: 20px;
        background: var(--primary-color, #008060);
        color: #ffffff;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.05em;
        padding: 4px 10px;
        border-radius: 9999px;
    }
    .pricing-card-header h3 {
        margin: 0 0 10px 0;
        font-size: 20px;
        font-weight: 700;
        color: var(--text-main, #0f172a);
    }
    .pricing-card-header p {
        margin: 0;
        font-size: 13.5px;
        color: var(--text-muted, #64748b);
        line-height: 1.5;
    }
    .pricing-price {
        margin: 24px 0;
        display: flex;
        align-items: baseline;
    }
    .pricing-price .amount {
        font-size: 36px;
        font-weight: 800;
        color: var(--text-main, #0f172a);
    }
    .pricing-price .period {
        margin-left: 4px;
        font-size: 14px;
        color: var(--text-muted, #64748b);
    }
    .pricing-features {
        list-style: none;
        padding: 0;
        margin: 0 0 30px 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .pricing-features li {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13.5px;
        color: var(--text-main, #0f172a);
    }
    .pricing-features li svg {
        color: var(--primary-color, #008060);
        flex-shrink: 0;
    }
    .pricing-features li.disabled {
        color: var(--text-muted, #94a3b8);
        text-decoration: line-through;
    }
    .pricing-features li.disabled svg {
        color: var(--text-muted, #94a3b8);
    }
    .pricing-button {
        width: 100%;
        padding: 12px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        text-align: center;
        cursor: pointer;
        transition: all 0.15s ease;
        border: 1px solid var(--border-color, #e2e8f0);
        background: #ffffff;
        color: var(--text-main, #0f172a);
    }
    .pricing-button.primary {
        background: var(--primary-color, #008060);
        border-color: var(--primary-color, #008060);
        color: #ffffff;
        text-decoration: none;
        display: block;
        box-sizing: border-box;
    }
    .pricing-button.primary:hover {
        background: var(--primary-hover, #006e52);
        border-color: var(--primary-hover, #006e52);
    }
    .pricing-button.disabled {
        background: #f1f5f9;
        border-color: #e2e8f0;
        color: var(--text-muted, #94a3b8);
        cursor: default;
        pointer-events: none;
        display: block;
        box-sizing: border-box;
    }
    /* Warning Alert Banner */
    .limit-warning-banner {
        background-color: #fffbeb;
        border: 1px solid #fef3c7;
        border-left: 4px solid #d97706;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 24px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .limit-warning-banner .warning-icon {
        font-size: 20px;
        line-height: 1;
    }
    .limit-warning-banner .warning-content h4 {
        margin: 0 0 4px 0;
        font-size: 14px;
        font-weight: 700;
        color: #92400e;
    }
    .limit-warning-banner .warning-content p {
        margin: 0 0 12px 0;
        font-size: 13px;
        color: #b45309;
        line-height: 1.5;
    }
    .limit-warning-banner .warning-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: #d97706;
        border: 1px solid #d97706;
        color: #ffffff;
        font-weight: 600;
        font-size: 12.5px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.15s;
        text-decoration: none;
    }
    .limit-warning-banner .warning-action-btn:hover {
        background: #b45309;
        border-color: #b45309;
    }

    /* Hide custom sidebar to use Shopify's native sidebar instead */
    .sidebar {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 24px;
    }
    /* s-button layout overrides to ensure proper spacing and prevent line wrapping */
    s-button {
        display: inline-flex !important;
        margin: 0;
        vertical-align: middle;
    }
</style>

<s-app-nav>
    <s-link href="/" rel="home">Overview</s-link>
    <s-link href="/bookings">Bookings & Deposits</s-link>
    <s-link href="/reminders">Reminders</s-link>
    <s-link href="/price-alerts">Price Alerts</s-link>
    <s-link href="/app-settings">Settings</s-link>
    <s-link href="/how-it-works">How It Works</s-link>
    <s-link href="/benefits">Benefits</s-link>
    <s-link href="/price-plan">Price Plan</s-link>
</s-app-nav>

<div class="app-layout">

<!-- ─────────────── SIDEBAR ─────────────── -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <h2>Buy<span>Later</span></h2>
        <small>Store Dashboard</small>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Main</div>
        <button class="sidebar-btn {{ $activeTab === 'tab-overview' ? 'active' : '' }}" onclick="switchTab(event, 'tab-overview')">
            <span class="icon">📈</span> Overview
        </button>
        <button class="sidebar-btn {{ $activeTab === 'tab-bookings-list' ? 'active' : '' }}" onclick="switchTab(event, 'tab-bookings-list')">
            <span class="icon">💰</span> Bookings &amp; Deposits
        </button>
        <button class="sidebar-btn {{ $activeTab === 'tab-reminders-list' ? 'active' : '' }}" onclick="switchTab(event, 'tab-reminders-list')">
            <span class="icon">⏰</span> Reminders
        </button>
        <button class="sidebar-btn {{ $activeTab === 'tab-subscribers-list' ? 'active' : '' }}" onclick="switchTab(event, 'tab-subscribers-list')">
            <span class="icon">🔔</span> Price Alerts
        </button>

        <hr class="sidebar-divider">
        <div class="sidebar-section-label">App</div>
        <button class="sidebar-btn {{ $activeTab === 'tab-settings' ? 'active' : '' }}" onclick="switchTab(event, 'tab-settings')">
            <span class="icon">⚙️</span> Settings
        </button>

        <hr class="sidebar-divider">
        <div class="sidebar-section-label">Guide</div>
        <button class="sidebar-btn {{ $activeTab === 'tab-how-it-works' ? 'active' : '' }}" onclick="switchTab(event, 'tab-how-it-works')">
            <span class="icon">📖</span> How It Works
        </button>
        <button class="sidebar-btn {{ $activeTab === 'tab-benefits' ? 'active' : '' }}" onclick="switchTab(event, 'tab-benefits')">
            <span class="icon">✨</span> Benefits
        </button>
        <button class="sidebar-btn {{ $activeTab === 'tab-pricing' ? 'active' : '' }}" onclick="switchTab(event, 'tab-pricing')">
            <span class="icon">💳</span> Price Plan
        </button>
    </nav>
</aside>

<!-- ─────────────── MAIN CONTENT ─────────────── -->
<div class="main-content">
<div class="dashboard-container">

    @php
        $shop = auth()->user();
        $isFreemium = $shop->isFreemium();
    @endphp

    <div class="filter-toolbar-container">
        <div class="filter-presets">
            <button class="filter-btn {{ $dateFilter == 'all' ? 'active' : '' }}" onclick="applyDateFilter('all')">All Time</button>
            <button class="filter-btn {{ $dateFilter == 'today' ? 'active' : '' }}" onclick="applyDateFilter('today')">Today</button>
            <button class="filter-btn {{ $dateFilter == 'week' ? 'active' : '' }}" onclick="applyDateFilter('week')">This Week</button>
        </div>
        <div class="date-picker-wrapper">
            <button type="button" class="date-picker-activator" id="date_picker_activator_btn">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: #6d7175;">
                    <rect x="3" y="4" width="14" height="14" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="17" y2="10"></line>
                </svg>
                <span>
                    @if($dateFilter === 'custom' && $start && $end)
                        {{ $start->format('M d, Y') }} – {{ $end->format('M d, Y') }}
                    @else
                        Select Date
                    @endif
                </span>
                <svg width="12" height="12" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 8px; color: #6d7175;">
                    <polyline points="6 9 10 13 14 9"></polyline>
                </svg>
            </button>
            
            <div class="date-picker-popover" id="date_picker_popover">
                <div class="popover-content">
                    <s-date-picker
                        id="shopify_date_picker"
                        type="range"
                        name="date-range"
                        value="{{ $start && $end ? $start->toDateString().'--'.$end->toDateString() : '' }}"
                        view="{{ $start ? $start->format('Y-m') : now()->format('Y-m') }}"
                    ></s-date-picker>
                </div>
                <div class="popover-actions">
                    <button type="button" class="popover-btn-cancel" id="popover_cancel_btn">Cancel</button>
                    <button type="button" class="popover-btn-apply" id="popover_apply_btn">Apply</button>
                </div>
            </div>
        </div>
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

    @php
        $usageStats = \App\Models\Booking::getUsageStats($shop->id);
        $usageCount = $usageStats['total'];
        $hasPlan = (bool) $shop->plan_id;
    @endphp

    @if(!$hasPlan && $usageCount >= 10)
        <div class="limit-warning-banner">
            <span class="warning-icon">⚠️</span>
            <div class="warning-content">
                <h4>Plan Limit Reached</h4>
                <p>You have used all <strong>{{ $usageCount }} of your 10 free combined reservations, reminders, and price drop alerts</strong>. Customers can no longer place holds or set reminders on your store. Please upgrade your plan to unlock unlimited usage.</p>
                <button type="button" onclick="switchTab(event, 'tab-pricing')" class="warning-action-btn">
                    Upgrade Plan
                </button>
            </div>
        </div>
    @elseif(!$hasPlan && $usageCount >= 8)
        <div class="limit-warning-banner" style="border-left-color: #f59e0b;">
            <span class="warning-icon">⚠️</span>
            <div class="warning-content">
                <h4>Approaching Plan Limit</h4>
                <p>You have used <strong>{{ $usageCount }} of your 10 free combined reservations, reminders, and price drop alerts</strong>. Upgrade now to ensure uninterrupted service for your customers.</p>
                <button type="button" onclick="switchTab(event, 'tab-pricing')" class="warning-action-btn" style="background: #f59e0b; border-color: #f59e0b;">
                    Upgrade Plan
                </button>
            </div>
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
            <div class="stat-info">
                <div class="stat-label" title="Total revenue generated from fully completed and paid orders">Revenue Recovered</div>
                <div class="stat-value">${{ number_format($revenueRecovered, 2) }}</div>
                <div class="stat-change">
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
            <div class="stat-visual">
                <svg width="64" height="28" viewBox="0 0 64 28" fill="none" stroke="#298dff" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 24 Q16 26 28 14 T60 4" />
                </svg>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-label" title="Active reservations currently on hold awaiting balance payment">Active Bookings</div>
                <div class="stat-value">{{ $activeBookings }}</div>
                <div class="stat-change">
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
            <div class="stat-visual">
                <svg width="64" height="28" viewBox="0 0 64 28" fill="none" stroke="#298dff" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 24 Q16 18 28 20 T60 8" />
                </svg>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-label" title="Automatic reminders scheduled to be sent to customers today">Scheduled Reminders</div>
                <div class="stat-value">{{ $todayRemindersCount }}</div>
                <div class="stat-change @if($todayRemindersCount > 0) change-up @else change-down @endif">
                    <span>{{ $todayRemindersCount > 0 ? 'Pending send today' : 'No alerts scheduled today' }}</span>
                </div>
            </div>
            <div class="stat-visual">
                <svg width="64" height="28" viewBox="0 0 64 28" fill="none" stroke="#6d7175" stroke-dasharray="3 3" stroke-width="1.5" stroke-linecap="round">
                    <path d="M4 14 H60" />
                </svg>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-label" title="Customers signed up to receive alerts when prices drop">Alert Subscribers</div>
                <div class="stat-value">{{ number_format($alertSubscribersCount) }}</div>
                <div class="stat-change">
                    <span>
                        @if($dateFilter === 'today')
                            Subscribers joined today
                        @elseif($dateFilter === 'week')
                            Subscribers joined this week
                        @elseif($dateFilter === 'custom')
                            Subscribers in custom range
                        @else
                            Total alert subscribers
                        @endif
                    </span>
                </div>
            </div>
            <div class="stat-visual">
                <svg width="64" height="28" viewBox="0 0 64 28" fill="none" stroke="#298dff" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 24 L20 18 L40 10 L60 4" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Hidden tab buttons for JS compatibility -->
    <div class="dashboard-tabs" aria-hidden="true" style="display: none;">
        <button class="tab-button active" onclick="switchTab(event, 'tab-overview')">Overview</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-bookings-list')">Bookings</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-reminders-list')">Reminders</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-subscribers-list')">Price Alerts</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-settings')">Settings</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-how-it-works')">How It Works</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-benefits')">Benefits</button>
        <button class="tab-button" onclick="switchTab(event, 'tab-pricing')">Price Plan</button>
    </div>

    <!-- Tab 1: Overview Dashboard -->
    <div id="tab-overview" class="tab-content" style="display: {{ $activeTab === 'tab-overview' ? 'block' : 'none' }};">
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
                                <span class="status-pill deposit_paid">Partial Paid</span>
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
                                <span class="status-pill deposit_paid">Partial Paid</span>
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
                                <span class="status-pill completed">Full Paid</span>
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
                                    <span class="status-pill {{ $booking->status }}">
                                        @if($booking->status === 'completed')
                                            Full Paid
                                        @elseif($booking->status === 'deposit_paid')
                                            Partial Paid
                                        @else
                                            {{ str_replace('_', ' ', $booking->status) }}
                                        @endif
                                    </span>
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
                        <span style="width: 10px; height: 10px; border-radius: 50%; background-color: #005ea2; display: inline-block;"></span>
                        <span style="color: var(--text-muted);">Partial Paid:</span>
                        <strong style="color: var(--text-main);">{{ $statusCounts['deposit_paid'] }}</strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background-color: #108043; display: inline-block;"></span>
                        <span style="color: var(--text-muted);">Full Paid:</span>
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
    <div id="tab-bookings-list" class="tab-content" style="display: {{ $activeTab === 'tab-bookings-list' ? 'block' : 'none' }};">
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
                        <option value="deposit_paid">Partial Paid</option>
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
                                            Deposit paid: <span style="font-weight:600; color:var(--secondary-color);">${{ number_format($booking->deposit_amount, 2) }} {{ $booking->currency ?: 'USD' }}</span><br>
                                            Remaining balance: <span style="font-weight:600; color:var(--accent-blue);">${{ number_format($booking->remaining_balance, 2) }} {{ $booking->currency ?: 'USD' }}</span><br>
                                            <span style="font-size:11.5px; color: var(--text-muted);">Total: ${{ number_format($booking->product_price, 2) }} {{ $booking->currency ?: 'USD' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $booking->status }}">
                                            @if($booking->status === 'completed')
                                                Full Paid
                                            @elseif($booking->status === 'deposit_paid')
                                                Partial Paid
                                            @else
                                                {{ str_replace('_', ' ', $booking->status) }}
                                            @endif
                                        </span>
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
                                        <div class="actions-cell" style="display: flex; gap: 8px;">
                                            <s-button variant="secondary" onclick="openBookingDetails({{ json_encode($booking) }})">Details</s-button>

                                            @if($booking->status !== 'completed' && $booking->status !== 'expired')
                                                <form action="{{ route('bookings.send_reminder', array_merge(['id' => $booking->id], request()->query())) }}" method="POST" style="margin:0;">
                                                    @csrf
                                                    <s-button submit="true" variant="secondary">Send Reminder</s-button>
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
    <div id="tab-reminders-list" class="tab-content" style="display: {{ $activeTab === 'tab-reminders-list' ? 'block' : 'none' }};">
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
    <div id="tab-subscribers-list" class="tab-content" style="display: {{ $activeTab === 'tab-subscribers-list' ? 'block' : 'none' }};">
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
    <div id="tab-settings" class="tab-content" style="display: {{ $activeTab === 'tab-settings' ? 'block' : 'none' }};">
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

            <!-- Card 1.5: Product Targeting -->
            <div class="panel-card" style="margin-bottom: 20px;">
                <h3>Product Targeting & Visibility</h3>
                <p style="font-size: 13px; color: var(--text-muted); margin-top: -10px; margin-bottom: 20px;">Choose where the "Buy Now Later" widget should be visible on your storefront.</p>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap; margin-top: 8px;">
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13.5px; font-weight: 500; color: var(--text-main);">
                            <input type="radio" name="product_targeting_type" value="all" {{ ($settings->product_targeting_type ?? 'all') === 'all' ? 'checked' : '' }} onchange="toggleProductSelector()" style="width: 16px; height: 16px; accent-color: var(--primary-color);">
                            Show on All Products
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13.5px; font-weight: 500; color: var(--text-main);">
                            <input type="radio" name="product_targeting_type" value="specific" {{ ($settings->product_targeting_type ?? 'all') === 'specific' ? 'checked' : '' }} onchange="toggleProductSelector()" style="width: 16px; height: 16px; accent-color: var(--primary-color);">
                            Show only on Selected Products
                        </label>
                    </div>
                </div>

                <!-- Product Selector container -->
                <div id="product-selector-container" style="display: {{ ($settings->product_targeting_type ?? 'all') === 'specific' ? 'block' : 'none' }}; border-top: 1px solid var(--border-color); padding-top: 20px; margin-top: 16px;">
                    <div class="form-group" style="position: relative; margin-bottom: 16px;">
                        <label for="product-search-input" style="font-weight: 600; margin-bottom: 8px; display: block;">Search Products to Add</label>
                        <div class="search-input-wrapper" style="width: 100%; max-width: none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" id="product-search-input" placeholder="Type product name..." oninput="handleProductSearch()" style="width: 100%; border: none; background: transparent; outline: none; padding: 8px 0; color: var(--text-main); font-size: 13.5px;">
                        </div>
                        <!-- Search dropdown results -->
                        <div id="product-search-results" style="position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid var(--border-color); border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: none; z-index: 1000; max-height: 250px; overflow-y: auto; margin-top: 4px;"></div>
                    </div>

                    <div>
                        <label style="font-weight: 600; margin-bottom: 12px; display: block;">Selected Products</label>
                        <div id="selected-products-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 300px; overflow-y: auto; background: #fafafa; border: 1px solid var(--border-color); border-radius: 6px; padding: 12px;">
                            <!-- Products will be added dynamically here -->
                        </div>
                    </div>

                    <input type="hidden" id="targeted_product_ids" name="targeted_product_ids" value="{{ $settings->targeted_product_ids }}">
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
                    <textarea id="reminder_email_template" name="reminder_email_template" rows="8">{{ $settings->reminder_email_template ?? '<!DOCTYPE html>
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
</html>' }}</textarea>
                </div>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 24px 0;">
                <div class="form-group">
                    <label for="discount_email_subject">Price Drop Email Subject</label>
                    <input type="text" id="discount_email_subject" name="discount_email_subject" value="{{ $settings->discount_email_subject }}" required>
                </div>
                <div class="form-group">
                    <label for="discount_email_template">Price Drop Email HTML Template</label>
                    <textarea id="discount_email_template" name="discount_email_template" rows="8">{{ $settings->discount_email_template ?? '<!DOCTYPE html>
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
</html>' }}</textarea>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn-save">Save All Settings</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Tab 6: How It Works -->
    <div id="tab-how-it-works" class="tab-content" style="display: {{ $activeTab === 'tab-how-it-works' ? 'block' : 'none' }};">
        <div class="guide-header">
            <h2>How It Works</h2>
            <p>Understand the core features, options flow, and customer journey of the Buy Later app.</p>
        </div>

        <div class="panel-card" style="margin-bottom: 24px;">
            <h3>🔄 Storefront Customer Journey</h3>
            <div class="guide-timeline">
                <div class="guide-timeline-item">
                    <h5>1. Trigger Widget on Product Page</h5>
                    <p>The "Buy Later" button is placed seamlessly next to or below your standard add-to-cart button using Shopify App Blocks. It dynamically loads configuration settings (colors, fonts, allowed options) directly from the application database.</p>
                </div>
                <div class="guide-timeline-item">
                    <h5>2. Option Selection Modal</h5>
                    <p>Clicking the button opens an attractive, clean modal presenting three flexible options to the customer: Book with Deposit, Set a Reminder, or Subscribe to Discount Alerts.</p>
                </div>
                <div class="guide-timeline-item">
                    <h5>3. Processing the Selection</h5>
                    <p>
                        • <strong>Book It Now (Partial Paid)</strong>: The customer provides their email, pays the required deposit (e.g. 10%), and is redirected to checkout. A secure hold is created, and the status updates in your dashboard.<br>
                        • <strong>Remind Me Later</strong>: The customer picks a custom date and time to receive an automated follow-up email containing a direct link to the product.<br>
                        • <strong>Alert Me on Discount</strong>: Subscribes the customer to instant price drop alerts for the specific product.
                    </p>
                </div>
                <div class="guide-timeline-item">
                    <h5>4. Completing the Purchase</h5>
                    <p>For deposit holds, customers can revisit their portal to pay the remaining balance, settling the draft order. Reminder and discount emails contain direct purchase links to ensure quick conversion.</p>
                </div>
            </div>
        </div>

        <div class="guide-grid-3">
            <div class="guide-card-premium">
                <div class="guide-card-badge">💰</div>
                <h4>Deposit Holds</h4>
                <p>Ensures immediate cash flow by collecting partial payments while reserving high-demand items for customers.</p>
            </div>
            <div class="guide-card-premium">
                <div class="guide-card-badge">⏰</div>
                <h4>Automated Reminders</h4>
                <p>Draft orders are linked directly inside scheduler tasks to send professional reminder emails exactly when requested.</p>
            </div>
            <div class="guide-card-premium">
                <div class="guide-card-badge">🔔</div>
                <h4>Price Alert Engine</h4>
                <p>Scans price updates across products and variants, immediately emailing subscribers if a discount is published.</p>
            </div>
        </div>

        <div class="panel-card" style="margin-top: 32px; border-top: 4px solid var(--primary-color);">
            <h3>✉️ Feedback &amp; Complaint Form</h3>
            <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 20px;">
                Have a feature suggestion, found a bug, or want to register a complaint? Let us know directly. We value your input and respond to support messages within 24 hours.
            </p>
            
            <form id="feedback-form" style="display: flex; flex-direction: column; gap: 16px;">
                <div class="guide-grid-2" style="margin-bottom: 0; gap: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="feedback_type" style="font-weight: 500;">Feedback Type</label>
                        <select id="feedback_type" name="feedback_type" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit;" required>
                            <option value="General Feedback">General Feedback</option>
                            <option value="Bug Report">Report a Bug</option>
                            <option value="Feature Request">Feature Request</option>
                            <option value="Complaint">Complaint</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="feedback_contact" style="font-weight: 500;">Contact Email</label>
                        <input type="email" id="feedback_contact" name="feedback_contact" value="{{ $shop->email ?? '' }}" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit;" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="feedback_subject" style="font-weight: 500;">Subject</label>
                    <input type="text" id="feedback_subject" name="feedback_subject" placeholder="What is this about?" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit;" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="feedback_message" style="font-weight: 500;">Message</label>
                    <textarea id="feedback_message" name="feedback_message" rows="4" placeholder="Detail your feedback, suggestion or complaint..." style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; resize: vertical;" required></textarea>
                </div>

                <div style="text-align: right;">
                    <button type="submit" id="feedback-submit-btn" class="btn-save" style="margin-top: 8px;">Submit Feedback</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab 7: Benefits -->
    <div id="tab-benefits" class="tab-content" style="display: {{ $activeTab === 'tab-benefits' ? 'block' : 'none' }};">
        <div class="guide-header">
            <h2>Merchant &amp; Customer Benefits</h2>
            <p>Discover how the Buy Later suite increases conversions, prevents cart abandonment, and drives customer loyalty.</p>
        </div>

        <div class="guide-grid-2">
            <div class="guide-card-premium">
                <div class="guide-card-badge">🚀</div>
                <h4>Boost Storefront Conversions</h4>
                <p>Lower the barrier to entry by offering low-deposit holds (e.g. 10% or 15% upfront). Customers who aren't ready to pay the full price today can lock in their purchase instantly, boosting your immediate sale counts.</p>
            </div>
            <div class="guide-card-premium">
                <div class="guide-card-badge">📉</div>
                <h4>Minimize Cart Abandonment</h4>
                <p>Instead of leaving empty-handed when budget or timing isn't right, users can save products using custom reminders or discount alerts. This acts as a warm lead generator, keeping your brand fresh in their inbox.</p>
            </div>
            <div class="guide-card-premium">
                <div class="guide-card-badge">📧</div>
                <h4>High-Quality Retargeting List</h4>
                <p>Every reminder request and price drop alert subscription captures the customer's email with their direct consent. Build an active list of warm leads with high buying intent for your store's products.</p>
            </div>
            <div class="guide-card-premium">
                <div class="guide-card-badge">🔒</div>
                <h4>Secure &amp; Compliant Holds</h4>
                <p>All deposit reservations leverage Shopify's native Draft Order API, securing stock allocation and keeping inventory levels fully synchronized with your Shopify admin. Zero manual order reconciliations are required.</p>
            </div>
        </div>

        <div class="panel-card">
            <h3>✨ Customer Experience Perks</h3>
            <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.6; margin: 0 0 16px 0;">
                Shoppers love flexibility. By integrating Buy Later options, you offer an upscale shopping experience similar to modern retail layaway systems.
            </p>
            <div class="guide-grid-3" style="margin-bottom: 0;">
                <div style="background: rgba(0,128,96,0.03); padding: 16px; border-radius: 8px;">
                    <h5 style="margin:0 0 6px 0; font-size:13.5px; font-weight:600; color:var(--primary-color);">No credit checks</h5>
                    <p style="margin:0; font-size:12.5px; color:var(--text-muted);">Risk-free reservation options with zero debt or interest charges.</p>
                </div>
                <div style="background: rgba(0,128,96,0.03); padding: 16px; border-radius: 8px;">
                    <h5 style="margin:0 0 6px 0; font-size:13.5px; font-weight:600; color:var(--primary-color);">Secure checkouts</h5>
                    <p style="margin:0; font-size:12.5px; color:var(--text-muted);">Processed directly through Shopify's secure storefront checkout.</p>
                </div>
                <div style="background: rgba(0,128,96,0.03); padding: 16px; border-radius: 8px;">
                    <h5 style="margin:0 0 6px 0; font-size:13.5px; font-weight:600; color:var(--primary-color);">Self-serve portal</h5>
                    <p style="margin:0; font-size:12.5px; color:var(--text-muted);">Storefront proxy lets customers check their balance and complete orders anytime.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Price Plan -->
    <div id="tab-pricing" class="tab-content" style="display: {{ $activeTab === 'tab-pricing' ? 'block' : 'none' }};">
        <div class="guide-header">
            <h2>Select Price Plan</h2>
            <p>Upgrade to unlock unlimited reservations, reminders, and price drop notifications.</p>
        </div>

        <div class="pricing-grid">
            <!-- Free Plan -->
            <div class="pricing-card">
                <div>
                    <div class="pricing-card-header">
                        <h3>Free Plan</h3>
                        <p>Perfect for testing and getting started with holds & alerts.</p>
                    </div>
                    <div class="pricing-price">
                        <span class="amount">$0</span>
                        <span class="period">/ month</span>
                    </div>
                    <ul class="pricing-features">
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Up to 10 combined items (Holds, Reminders, Alerts)
                        </li>
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Standard Email support
                        </li>
                        <li class="disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Unlimited customer reservations
                        </li>
                    </ul>
                </div>
                <div>
                    @if(!$hasPlan)
                        <s-button disabled="true" style="margin: 0; width: 100%;">Current Plan</s-button>
                    @else
                        <s-button disabled="true" style="margin: 0; width: 100%;">Free Tier</s-button>
                    @endif
                </div>
            </div>

            <!-- Premium Plan -->
            <div class="pricing-card popular">
                <div>
                    <div class="pricing-card-header">
                        <h3>Premium Plan</h3>
                        <p>Unlimited reservations, holds, reminders, and drop alerts.</p>
                    </div>
                    <div class="pricing-price">
                        <span class="amount">$5</span>
                        <span class="period">/ month</span>
                    </div>
                    <ul class="pricing-features">
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <strong>Unlimited</strong> reservations & holds
                        </li>
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <strong>Unlimited</strong> email reminders
                        </li>
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <strong>Unlimited</strong> price drop alerts
                        </li>
                        <li>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Priority support (Email & Live Chat)
                        </li>
                    </ul>
                </div>
                <div>
                    @if($hasPlan)
                        <s-button disabled="true" style="margin: 0; width: 100%;">Current Plan</s-button>
                    @else
                        <s-button href="{{ route('billing', array_merge(['plan' => 1], request()->query())) }}" variant="primary" target="_top" style="margin: 0; width: 100%;">Upgrade to Premium</s-button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    </div><!-- /.dashboard-container -->
</div><!-- /.main-content -->
</div><!-- /.app-layout -->

<!-- Details Modal -->
<div id="booking-details-modal" class="details-modal" onclick="closeBookingDetailsModalOnOutsideClick(event)">
    <div class="details-modal-content">
        <div class="details-modal-header">
            <h3>Booking & Reservation Details</h3>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" class="btn-action-primary" onclick="printBookingDetails()" style="padding: 4px 8px; font-size: 11.5px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                    🖨️ Print PDF
                </button>
                <button type="button" class="details-modal-close" onclick="closeBookingDetailsModal()">✕</button>
            </div>
        </div>
        <div class="details-modal-body">
            <div class="details-grid">
                <!-- Store Info -->
                <div class="details-section-title">Store Information</div>
                <div class="details-item full-width">
                    <label>Store Name</label>
                    <span id="detail-store-name">-</span>
                </div>

                <!-- Customer Info -->
                <div class="details-section-title">Customer Information</div>
                <div class="details-item">
                    <label>Customer Name</label>
                    <span id="detail-customer-name">-</span>
                </div>
                <div class="details-item">
                    <label>Email Address</label>
                    <span id="detail-customer-email">-</span>
                </div>

                <!-- Product Info -->
                <div class="details-section-title">Product & Reservation Info</div>
                <div class="details-item full-width">
                    <label>Product Title</label>
                    <span id="detail-product-title">-</span>
                </div>
                <div class="details-item">
                    <label>Original Price</label>
                    <span id="detail-product-price">-</span>
                </div>
                <div class="details-item">
                    <label>Booking Status</label>
                    <div class="details-badge-wrapper" id="detail-status-badge">
                        <span class="status-pill">-</span>
                    </div>
                </div>

                <!-- Financial Breakdown -->
                <div class="details-section-title">Payment Breakdown</div>
                <div class="details-item">
                    <label>Deposit Paid</label>
                    <span id="detail-deposit-amount" style="color: var(--secondary-color); font-weight: 600;">-</span>
                </div>
                <div class="details-item">
                    <label>Remaining Balance</label>
                    <span id="detail-remaining-balance" style="color: var(--accent-blue); font-weight: 600;">-</span>
                </div>

                <!-- Timeline / Dates -->
                <div class="details-section-title">Timeline & Dates</div>
                <div class="details-item">
                    <label>Booking Created At</label>
                    <span id="detail-created-at">-</span>
                </div>
                <div class="details-item">
                    <label>Hold Expiry Date</label>
                    <span id="detail-expiry-date">-</span>
                </div>
                <div class="details-item">
                    <label>Deposit Paid Date</label>
                    <span id="detail-deposit-paid-at" style="color: var(--secondary-color);">-</span>
                </div>
                <div class="details-item">
                    <label>Full Paid Date</label>
                    <span id="detail-completed-at" style="color: var(--primary-color);">-</span>
                </div>

                <!-- Shopify Orders -->
                <div class="details-section-title">Shopify Orders</div>
                <div class="details-item">
                    <label>Initial Deposit Order</label>
                    <span id="detail-deposit-order-link">-</span>
                </div>
                <div class="details-item">
                    <label>Remaining Balance Order</label>
                    <span id="detail-balance-order-link">-</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CURRENT_SHOP_DOMAIN = "{{ auth()->user()->name }}";
const CURRENT_SHOP_NAME = "{{ ucwords(str_replace(['-', '_'], ' ', str_replace('.myshopify.com', '', auth()->user()->name))) }}";

// Booking Details Modal Handlers
function openBookingDetails(booking) {
    // Helper to format currency
    const formatCurrency = (val) => {
        const cur = booking.currency || 'USD';
        try {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: cur }).format(val);
        } catch (e) {
            return parseFloat(val || 0).toFixed(2) + ' ' + cur;
        }
    };

    // Helper to format date
    const formatDate = (dateStr) => {
        if (!dateStr) return 'Not Yet / Pending';
        const date = new Date(dateStr);
        return date.toLocaleString();
    };

    // Populate Fields
    document.getElementById('detail-store-name').innerText = CURRENT_SHOP_NAME + ' (' + CURRENT_SHOP_DOMAIN + ')';
    document.getElementById('detail-customer-name').innerText = booking.customer_name || 'N/A';
    document.getElementById('detail-customer-email').innerText = booking.email || '-';
    document.getElementById('detail-product-title').innerText = booking.product_title || '-';
    document.getElementById('detail-product-price').innerText = formatCurrency(booking.product_price);
    document.getElementById('detail-deposit-amount').innerText = formatCurrency(booking.deposit_amount);
    document.getElementById('detail-remaining-balance').innerText = formatCurrency(booking.remaining_balance);
    
    document.getElementById('detail-created-at').innerText = formatDate(booking.created_at);
    document.getElementById('detail-expiry-date').innerText = formatDate(booking.expires_at);
    document.getElementById('detail-deposit-paid-at').innerText = formatDate(booking.deposit_paid_at);
    document.getElementById('detail-completed-at').innerText = formatDate(booking.completed_at);

    // Render Status Badge
    const badgeWrapper = document.getElementById('detail-status-badge');
    let statusText = booking.status;
    if (booking.status === 'deposit_paid') statusText = 'Partial Paid';
    if (booking.status === 'completed') statusText = 'Full Paid';
    badgeWrapper.innerHTML = `<span class="status-pill ${booking.status}">${statusText.toUpperCase()}</span>`;

    // Render Shopify Order Links
    const depositLinkContainer = document.getElementById('detail-deposit-order-link');
    const balanceLinkContainer = document.getElementById('detail-balance-order-link');

    // Initial Deposit Order Link
    if (booking.order_id) {
        const urlParams = new URLSearchParams(window.location.search);
        const shopDomain = urlParams.get('shop') || 'canny-apps.myshopify.com';
        const adminUrl = `https://admin.shopify.com/store/${shopDomain.replace('.myshopify.com', '')}/orders/${booking.order_id}`;
        depositLinkContainer.innerHTML = `<a href="${adminUrl}" class="shopify-link" target="_blank">Order #${booking.order_id} ↗</a>`;
    } else {
        depositLinkContainer.innerHTML = `<span style="color: var(--text-muted);">Not created yet</span>`;
    }

    // Remaining Balance Order Link
    if (booking.balance_order_id) {
        const urlParams = new URLSearchParams(window.location.search);
        const shopDomain = urlParams.get('shop') || 'canny-apps.myshopify.com';
        const adminUrl = `https://admin.shopify.com/store/${shopDomain.replace('.myshopify.com', '')}/orders/${booking.balance_order_id}`;
        balanceLinkContainer.innerHTML = `<a href="${adminUrl}" class="shopify-link" target="_blank">Order #${booking.balance_order_id} ↗</a>`;
    } else {
        balanceLinkContainer.innerHTML = `<span style="color: var(--text-muted);">-</span>`;
    }

    // Show Modal
    document.getElementById('booking-details-modal').classList.add('show');
}

function closeBookingDetailsModal() {
    document.getElementById('booking-details-modal').classList.remove('show');
}

function closeBookingDetailsModalOnOutsideClick(event) {
    const modal = document.getElementById('booking-details-modal');
    if (event.target === modal) {
        closeBookingDetailsModal();
    }
}

function printBookingDetails() {
    const modalContent = document.querySelector('#booking-details-modal .details-modal-content').cloneNode(true);
    
    // Remove the header actions wrapper containing close/print buttons
    const headerActions = modalContent.querySelector('.details-modal-header div');
    if (headerActions) headerActions.remove();
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Reservation Details</title>');
    
    // Add print styles
    printWindow.document.write(`
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 40px; color: #0f172a; }
            .details-modal-header { border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 24px; }
            .details-modal-header h3 { margin: 0; font-size: 20px; font-weight: 700; }
            .details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
            .details-section-title { grid-column: span 2; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 16px; }
            .details-item { display: flex; flex-direction: column; gap: 4px; }
            .details-item.full-width { grid-column: span 2; }
            .details-item label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; }
            .details-item span { font-weight: 500; font-size: 14px; }
            .status-pill { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
            .status-pill.deposit_paid { background: #fef3c7; color: #92400e; }
            .status-pill.completed { background: #d1fae5; color: #065f46; }
            .status-pill.pending { background: #fef3c7; color: #92400e; }
            .status-pill.expired { background: #fee2e2; color: #991b1b; }
            .shopify-link { color: #1a1a1a; text-decoration: none; font-weight: 600; }
            @media print {
                body { padding: 0; }
            }
        </style>
    `);
    
    printWindow.document.write('</head><body>');
    printWindow.document.write(modalContent.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    
    setTimeout(() => {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    }, 250);
}

// Product Targeting Selector
let selectedProducts = @json($targetedProducts) || [];

function toggleProductSelector() {
    const container = document.getElementById('product-selector-container');
    const selectedRadio = document.querySelector('input[name="product_targeting_type"]:checked');
    if (container && selectedRadio) {
        container.style.display = selectedRadio.value === 'specific' ? 'block' : 'none';
    }
}

function renderSelectedProducts() {
    const listEl = document.getElementById('selected-products-list');
    const hiddenInput = document.getElementById('targeted_product_ids');
    if (!listEl || !hiddenInput) return;

    listEl.innerHTML = '';
    
    if (selectedProducts.length === 0) {
        listEl.innerHTML = '<div style="color: var(--text-muted); font-size: 13px; text-align: center; padding: 12px 0;">No products selected. Search above to add products.</div>';
        hiddenInput.value = '';
        return;
    }

    selectedProducts.forEach(prod => {
        const item = document.createElement('div');
        item.style.cssText = 'display: flex; align-items: center; justify-content: space-between; background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);';
        
        const imgHtml = prod.image 
            ? `<img src="${prod.image}" alt="${prod.title}" style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px; margin-right: 12px; border: 1px solid var(--border-color);"/>`
            : `<div style="width: 32px; height: 32px; border-radius: 4px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 14px; margin-right: 12px; border: 1px solid var(--border-color);">📦</div>`;

        item.innerHTML = `
            <div style="display: flex; align-items: center; overflow: hidden; flex: 1; margin-right: 12px;">
                ${imgHtml}
                <span style="font-size: 13.5px; font-weight: 500; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${prod.title}</span>
            </div>
            <button type="button" onclick="removeProduct('${prod.id}')" style="background: none; border: none; color: #d82c0d; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 4px; border-radius: 4px; transition: background 0.15s ease;" onmouseover="this.style.background='rgba(216,44,13,0.05)'" onmouseout="this.style.background='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        `;
        listEl.appendChild(item);
    });

    hiddenInput.value = selectedProducts.map(p => p.id).join(',');
}

function removeProduct(id) {
    selectedProducts = selectedProducts.filter(p => String(p.id) !== String(id));
    renderSelectedProducts();
}

let searchTimeout = null;
async function handleProductSearch() {
    const input = document.getElementById('product-search-input');
    const resultsEl = document.getElementById('product-search-results');
    if (!input || !resultsEl) return;

    const query = input.value.trim();
    if (query.length < 2) {
        resultsEl.style.display = 'none';
        resultsEl.innerHTML = '';
        return;
    }

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        resultsEl.innerHTML = '<div style="padding: 12px; text-align: center; font-size: 13px; color: var(--text-muted);">Searching...</div>';
        resultsEl.style.display = 'block';

        let token = '';
        try {
            if (window.shopify && typeof window.shopify.idToken === 'function') {
                token = await window.shopify.idToken();
            }
        } catch (err) {
            console.error('Failed to retrieve Shopify session token for product search:', err);
        }

        const url = new URL('{{ route("products.search") }}', window.location.origin);
        const searchParams = new URLSearchParams(window.location.search);
        for (const [key, val] of searchParams.entries()) {
            url.searchParams.set(key, val);
        }
        url.searchParams.set('q', query);
        if (token) {
            url.searchParams.set('token', token);
        }

        try {
            const res = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!res.ok) throw new Error('Search failed');
            
            const products = await res.json();
            resultsEl.innerHTML = '';

            if (products.length === 0) {
                resultsEl.innerHTML = '<div style="padding: 12px; text-align: center; font-size: 13px; color: var(--text-muted);">No products found.</div>';
                return;
            }

            products.forEach(prod => {
                const isAlreadySelected = selectedProducts.some(p => String(p.id) === String(prod.id));
                const item = document.createElement('div');
                item.style.cssText = 'display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.15s ease;';
                item.onmouseover = () => { item.style.background = '#f5f5f5'; };
                item.onmouseout = () => { item.style.background = '#ffffff'; };
                
                const imgHtml = prod.image 
                    ? `<img src="${prod.image}" alt="${prod.title}" style="width: 28px; height: 28px; object-fit: cover; border-radius: 4px; margin-right: 10px; border: 1px solid var(--border-color);"/>`
                    : `<div style="width: 28px; height: 28px; border-radius: 4px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 10px; border: 1px solid var(--border-color);">📦</div>`;

                const actionBtn = isAlreadySelected
                    ? `<span style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Added</span>`
                    : `<button type="button" style="background: var(--primary-color); color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 500; cursor: pointer; transition: opacity 0.15s ease;">Add</button>`;

                item.innerHTML = `
                    <div style="display: flex; align-items: center; overflow: hidden; flex: 1; margin-right: 12px;">
                        ${imgHtml}
                        <span style="font-size: 13px; font-weight: 500; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${prod.title}</span>
                    </div>
                    <div>${actionBtn}</div>
                `;

                if (!isAlreadySelected) {
                    item.querySelector('button').addEventListener('click', (e) => {
                        e.stopPropagation();
                        selectedProducts.push(prod);
                        renderSelectedProducts();
                        input.value = '';
                        resultsEl.style.display = 'none';
                        resultsEl.innerHTML = '';
                    });
                }

                resultsEl.appendChild(item);
            });
        } catch (err) {
            console.error('Error during product search:', err);
            resultsEl.innerHTML = '<div style="padding: 12px; text-align: center; font-size: 13px; color: #d82c0d;">Failed to load search results.</div>';
        }
    }, 300);
}

// Close search dropdown on click outside
document.addEventListener('click', function(e) {
    const resultsEl = document.getElementById('product-search-results');
    const input = document.getElementById('product-search-input');
    if (resultsEl && input && !resultsEl.contains(e.target) && e.target !== input) {
        resultsEl.style.display = 'none';
    }
});

// Initialize targeting UI on load
document.addEventListener('DOMContentLoaded', function() {
    renderSelectedProducts();
    
    // Toggle popover and handle native date-picker filter action
    const datePickerActivator = document.getElementById('date_picker_activator_btn');
    const datePickerPopover = document.getElementById('date_picker_popover');
    const shopifyDatePicker = document.getElementById('shopify_date_picker');
    const popoverCancelBtn = document.getElementById('popover_cancel_btn');
    const popoverApplyBtn = document.getElementById('popover_apply_btn');

    if (datePickerActivator && datePickerPopover) {
        datePickerActivator.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = datePickerPopover.style.display === 'flex';
            datePickerPopover.style.display = isVisible ? 'none' : 'flex';
        });

        document.addEventListener('click', function(e) {
            if (!datePickerPopover.contains(e.target) && e.target !== datePickerActivator && !datePickerActivator.contains(e.target)) {
                datePickerPopover.style.display = 'none';
            }
        });
    }

    if (popoverCancelBtn && datePickerPopover) {
        popoverCancelBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            datePickerPopover.style.display = 'none';
        });
    }

    if (popoverApplyBtn && shopifyDatePicker) {
        popoverApplyBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const val = shopifyDatePicker.value; // Format: "YYYY-MM-DD--YYYY-MM-DD"
            if (val && val.includes('--')) {
                const parts = val.split('--');
                const startDate = parts[0];
                const endDate = parts[1];
                applyDateFilter('custom', startDate, endDate);
            } else {
                datePickerPopover.style.display = 'none';
            }
        });
    }
    
    // Intercept clicks on s-link inside s-app-nav to perform instant SPA tab switching
    const links = document.querySelectorAll('s-app-nav s-link');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = link.getAttribute('href');
            
            // Map paths to tab IDs
            let tabId = 'tab-overview';
            if (href === '/bookings') {
                tabId = 'tab-bookings-list';
            } else if (href === '/reminders') {
                tabId = 'tab-reminders-list';
            } else if (href === '/price-alerts') {
                tabId = 'tab-subscribers-list';
            } else if (href === '/app-settings') {
                tabId = 'tab-settings';
            } else if (href === '/how-it-works') {
                tabId = 'tab-how-it-works';
            } else if (href === '/benefits') {
                tabId = 'tab-benefits';
            } else if (href === '/price-plan') {
                tabId = 'tab-pricing';
            }
            
            // Check if tab exists
            const targetEl = document.getElementById(tabId);
            if (targetEl) {
                e.preventDefault();
                e.stopPropagation();
                
                // Update history path while preserving shop/host parameters
                const currentSearch = window.location.search;
                const newUrl = href + currentSearch;
                history.pushState({ tabId: tabId }, '', newUrl);
                
                // Switch tab instantly
                switchTab(null, tabId);
            }
        });
    });

    // Handle back/forward buttons
    window.addEventListener('popstate', function(event) {
        const path = window.location.pathname;
        let tabId = 'tab-overview';
        if (path === '/bookings') {
            tabId = 'tab-bookings-list';
        } else if (path === '/reminders') {
            tabId = 'tab-reminders-list';
        } else if (path === '/price-alerts') {
            tabId = 'tab-subscribers-list';
        } else if (path === '/app-settings') {
            tabId = 'tab-settings';
        } else if (path === '/how-it-works') {
            tabId = 'tab-how-it-works';
        } else if (path === '/benefits') {
            tabId = 'tab-benefits';
        } else if (path === '/price-plan') {
            tabId = 'tab-pricing';
        }
        
        switchTab(null, tabId);
    });
});

    function switchTab(event, tabId) {
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => content.style.display = 'none');

        const tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(btn => btn.classList.remove('active'));

        const sidebarBtns = document.querySelectorAll('.sidebar-btn');
        sidebarBtns.forEach(btn => btn.classList.remove('active'));

        const targetEl = document.getElementById(tabId);
        if (targetEl) {
            targetEl.style.display = 'block';
        }

        // Set active on sidebar button
        const matchingSidebarBtn = Array.from(sidebarBtns).find(btn => {
            const onclick = btn.getAttribute('onclick') || '';
            return onclick.includes(tabId);
        });
        if (matchingSidebarBtn) {
            matchingSidebarBtn.classList.add('active');
        }

        // Set active on hidden tab button for compatibility
        const matchingTabBtn = Array.from(tabButtons).find(btn => {
            const onclick = btn.getAttribute('onclick') || '';
            return onclick.includes(tabId);
        });
        if (matchingTabBtn) {
            matchingTabBtn.classList.add('active');
        }

        // Push URL state if triggered inside the page via UI events
        if (event) {
            let path = '/';
            if (tabId === 'tab-bookings-list') path = '/bookings';
            else if (tabId === 'tab-reminders-list') path = '/reminders';
            else if (tabId === 'tab-subscribers-list') path = '/price-alerts';
            else if (tabId === 'tab-settings') path = '/app-settings';
            else if (tabId === 'tab-how-it-works') path = '/how-it-works';
            else if (tabId === 'tab-benefits') path = '/benefits';
            else if (tabId === 'tab-pricing') path = '/price-plan';

            const currentSearch = window.location.search;
            history.pushState({ tabId: tabId }, '', path + currentSearch);
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
            labels: ['Partial Paid', 'Full Paid', 'Expired'],
            datasets: [{
                data: [
                    {{ $statusCounts['deposit_paid'] }},
                    {{ $statusCounts['completed'] }},
                    {{ $statusCounts['expired'] }}
                ],
                backgroundColor: ['#005ea2', '#108043', '#d82c0d'],
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
            
            // Check if this is a Send Reminder form
            const isReminderForm = form.action && form.action.includes('/bookings/') && form.action.includes('/send-reminder');
            if (isReminderForm) {
                // Find the submit button in this form
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    // Disable button and show spinner
                    const originalHtml = btn.innerHTML;
                    btn.disabled = true;
                    btn.classList.add('loading');
                    btn.innerHTML = '<span class="spinner"></span> Sending...';
                    
                    // We need to fetch the Shopify session token first if we are inside Shopify Admin App Bridge
                    let token = '';
                    try {
                        if (window.shopify && typeof window.shopify.idToken === 'function') {
                            token = await window.shopify.idToken();
                        }
                    } catch (err) {
                        console.error('Failed to retrieve Shopify session token:', err);
                    }

                    // Prepare form data
                    const formData = new FormData(form);
                    if (token) {
                        formData.set('token', token);
                    }
                    
                    // Send request via Fetch API
                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        
                        // After fetch completes successfully
                        if (response.ok) {
                            btn.classList.remove('loading');
                            btn.classList.add('success-state');
                            btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="vertical-align: middle; margin-right: 4px; width: 12px; height: 12px;"><polyline points="20 6 9 17 4 12"></polyline></svg> Sent Success!`;
                            
                            // Reload the page after 1.5 seconds to refresh the booking lists/statuses/flash messages
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            // Fetch returned error
                            btn.disabled = false;
                            btn.classList.remove('loading');
                            btn.innerHTML = originalHtml;
                            alert('Failed to send reminder. Please try again.');
                        }
                    } catch (fetchErr) {
                        console.error('Error sending reminder:', fetchErr);
                        btn.disabled = false;
                        btn.classList.remove('loading');
                        btn.innerHTML = originalHtml;
                        alert('An error occurred. Please try again.');
                    }
                    return; // Stop here, do not submit normally
                }
            }

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

    // Feedback form submission handler
    const feedbackForm = document.getElementById('feedback-form');
    if (feedbackForm) {
        feedbackForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('feedback-submit-btn');
            if (!btn) return;

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Submitting...';

            let token = '';
            try {
                if (window.shopify && typeof window.shopify.idToken === 'function') {
                    token = await window.shopify.idToken();
                }
            } catch (err) {
                console.error('Failed to retrieve Shopify session token:', err);
            }

            const formData = new FormData(feedbackForm);
            const url = new URL('{{ route("feedback.submit") }}', window.location.origin);
            
            // Append query params for shop verification
            const searchParams = new URLSearchParams(window.location.search);
            for (const [key, val] of searchParams.entries()) {
                url.searchParams.set(key, val);
            }
            if (token) {
                url.searchParams.set('token', token);
            }

            try {
                const res = await fetch(url.toString(), {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    // Success
                    btn.innerHTML = 'Success!';
                    btn.style.background = '#108043';
                    feedbackForm.reset();
                    alert(data.message || 'Feedback submitted successfully. Thank you!');
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        btn.style.background = '';
                    }, 3000);
                } else {
                    throw new Error(data.message || 'Submission failed');
                }
            } catch (err) {
                console.error('Feedback error:', err);
                alert(err.message || 'Failed to submit feedback. Please try again.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    }
</script>

<!-- Crisp Live Chat Integration -->
<script type="text/javascript">
  window.$crisp=[];
  window.CRISP_WEBSITE_ID="69483358-0847-4a5c-9e02-ed71cbc48f1e";
  (function(){
    d=document;
    s=d.createElement("script");
    s.src="https://client.crisp.chat/l.js";
    s.async=1;
    d.getElementsByTagName("head")[0].appendChild(s);
  })();
</script>
@endsection
