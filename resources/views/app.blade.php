<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="shopify-api-key" content="{{ config('shopify-app.api_key') }}" />
        <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
        <title>{{ config('app.name', 'Buy Now Later') }}</title>
        @viteReactRefresh
        @vite(['resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body>
        <ui-nav-menu>
            <a href="/" rel="home">Overview</a>
            <a href="/bookings">Orders</a>
            <a href="/price-plan">Price Plan</a>
            <a href="/app-settings">General Settings</a>
            <a href="/support">Support & Helpdesk</a>
        </ui-nav-menu>
        @inertia
    </body>
</html>
