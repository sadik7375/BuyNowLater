<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Osiset\ShopifyApp\Actions\ActivatePlan;
use Osiset\ShopifyApp\Objects\Values\ChargeReference;
use Osiset\ShopifyApp\Objects\Values\PlanId;
use Osiset\ShopifyApp\Objects\Values\SessionToken;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Storage\Queries\Shop as ShopQuery;
use Osiset\ShopifyApp\Util;

class BillingController extends Controller
{
    /**
     * Shopify App Pricing (Managed Pricing) is enabled.
     * We do NOT use the Billing API / appSubscriptionCreate mutation.
     * Instead, we redirect directly to Shopify's hosted pricing plans page.
     *
     * URL format: https://admin.shopify.com/store/{store_handle}/charges/{api_key}/pricing_plans
     */
    public function index(
        Request $request,
        ShopQuery $shopQuery,
        ?int $plan = 1
    ) {
        try {
            // --- Resolve shop domain ---
            $shopDomainStr = $request->get('shop') ?: $request->query('shop') ?: $request->input('shop');

            if (empty($shopDomainStr)) {
                $tokenString = $request->get('id_token') ?: $request->get('token') ?: $request->header('Authorization');
                if ($tokenString) {
                    $tokenString = str_replace('Bearer ', '', $tokenString);
                    try {
                        $sessionToken = new SessionToken($tokenString, false);
                        $shopDomainStr = $sessionToken->getShopDomain()->toNative();
                    } catch (\Exception $e) {
                        Log::warning('BillingController: Could not parse SessionToken: ' . $e->getMessage());
                    }
                }
            }

            if (empty($shopDomainStr) && auth()->check()) {
                $shopDomainStr = auth()->user()->name;
            }

            if (empty($shopDomainStr)) {
                $firstUser = User::first();
                if ($firstUser) {
                    $shopDomainStr = $firstUser->name;
                }
            }

            if (empty($shopDomainStr)) {
                Log::error('BillingController: Shop domain could not be resolved.');
                return response()->json(['message' => 'Shop domain missing.'], 400);
            }

            // --- Build Shopify Managed Pricing URL ---
            $shopHandle = explode('.', $shopDomainStr)[0];
            $apiKey = Util::getShopifyConfig('api_key', ShopDomain::fromNative($shopDomainStr));

            // Shopify-hosted pricing plans page (Managed Pricing / Shopify App Pricing)
            $pricingUrl = "https://admin.shopify.com/store/{$shopHandle}/charges/{$apiKey}/pricing_plans";

            Log::info('Redirecting to Shopify Managed Pricing page:', [
                'shop' => $shopDomainStr,
                'url'  => $pricingUrl,
            ]);

            // --- Return iframe-breakout HTML page ---
            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="shopify-api-key" content="{$apiKey}" />
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <title>Upgrading to Premium...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Segoe UI", Roboto, sans-serif;
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; height: 100vh; margin: 0;
            background: #f6f6f7; color: #202223;
        }
        .spinner {
            width: 44px; height: 44px;
            border: 4px solid #e1e3e5;
            border-top: 4px solid #008060;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        h3 { margin: 0 0 8px 0; font-size: 18px; }
        p { margin: 0 0 20px 0; color: #6d7175; font-size: 14px; }
        .btn {
            padding: 12px 28px; background: #008060; color: white;
            text-decoration: none; border-radius: 8px; font-weight: 600;
            font-size: 15px; display: inline-block;
        }
        .btn:hover { background: #006e52; }
    </style>
</head>
<body>
    <div class="spinner"></div>
    <h3>Redirecting to Premium Plan...</h3>
    <p>You'll be taken to Shopify's secure billing page.</p>
    <a href="{$pricingUrl}" target="_top" class="btn">Select Plan &rarr;</a>

    <script>
        (function () {
            var url = "{$pricingUrl}";
            // Use App Bridge if available (embedded app)
            if (typeof shopify !== 'undefined' && shopify.navigation) {
                shopify.navigation.redirect(url);
            } else if (window.top && window.top !== window.self) {
                window.top.location.href = url;
            } else {
                window.location.href = url;
            }
        })();
    </script>
</body>
</html>
HTML;

            return response($html, 200)->header('Content-Type', 'text/html');

        } catch (\Exception $e) {
            Log::error('BillingController@index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Billing error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Processes the response after merchant approves/declines on Shopify billing page.
     * For Managed Pricing, Shopify handles plan activation automatically.
     * We just redirect back to home.
     */
    public function process(
        int $plan,
        Request $request,
        ShopQuery $shopQuery,
        ActivatePlan $activatePlan
    ): RedirectResponse {
        $shopDomainStr = $request->query('shop') ?: ($request->user() ? $request->user()->name : null);

        if (empty($shopDomainStr)) {
            $firstUser = User::first();
            if ($firstUser) {
                $shopDomainStr = $firstUser->name;
            }
        }

        $host = $request->get('host');
        if (empty($host) && !empty($shopDomainStr)) {
            $shopHandle = explode('.', $shopDomainStr)[0];
            $host = base64_encode("admin.shopify.com/store/{$shopHandle}");
        } else {
            $host = urldecode((string) $host);
        }

        $chargeId = $request->query('charge_id');

        if ($chargeId) {
            try {
                $shop = $shopQuery->getByDomain(ShopDomain::fromNative($shopDomainStr));
                $activatePlan(
                    $shop->getId(),
                    PlanId::fromNative($plan),
                    ChargeReference::fromNative((int) $chargeId),
                    $host
                );
                Log::info('Billing activated (charge_id flow):', ['shop' => $shopDomainStr, 'plan' => $plan]);
            } catch (\Exception $e) {
                Log::warning('BillingController@process activation error: ' . $e->getMessage());
            }
        } else {
            // Managed Pricing: Shopify activates the plan automatically, no charge_id returned
            Log::info('Managed Pricing: merchant returned from pricing page, redirecting home.', [
                'shop' => $shopDomainStr,
            ]);
        }

        return Redirect::route(Util::getShopifyConfig('route_names.home'), [
            'shop'   => $shopDomainStr,
            'host'   => $host,
            'locale' => $request->get('locale'),
        ]);
    }
}
