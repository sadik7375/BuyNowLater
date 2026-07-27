<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Osiset\ShopifyApp\Actions\GetPlanUrl;
use Osiset\ShopifyApp\Objects\Values\ChargeReference;
use Osiset\ShopifyApp\Objects\Values\NullablePlanId;
use Osiset\ShopifyApp\Objects\Values\PlanId;
use Osiset\ShopifyApp\Objects\Values\SessionToken;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Storage\Models\Plan as PlanModel;
use Osiset\ShopifyApp\Storage\Queries\Shop as ShopQuery;
use Osiset\ShopifyApp\Util;

class BillingController extends Controller
{
    /**
     * Initiates billing via Shopify GraphQL appSubscriptionCreate.
     * Requires Manual Pricing (Billing API) mode in Partner Dashboard.
     */
    public function index(
        Request $request,
        ShopQuery $shopQuery,
        GetPlanUrl $getPlanUrl,
        ?int $plan = 1
    ) {
        try {
            // --- Resolve shop domain ---
            $shopDomainStr = $request->get('shop')
                ?: $request->query('shop')
                ?: $request->input('shop');

            if (empty($shopDomainStr)) {
                $tokenString = $request->get('id_token')
                    ?: $request->get('token')
                    ?: $request->header('Authorization');
                if ($tokenString) {
                    $tokenString = str_replace('Bearer ', '', $tokenString);
                    try {
                        $sessionToken = new SessionToken($tokenString, false);
                        $shopDomainStr = $sessionToken->getShopDomain()->toNative();
                    } catch (\Exception $e) {
                        Log::warning('BillingController: SessionToken parse failed: ' . $e->getMessage());
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

            $shop   = $shopQuery->getByDomain(ShopDomain::fromNative($shopDomainStr));
            $planId = $plan ?: 1;

            // Resolve host
            $host = $request->get('host');
            if (empty($host)) {
                $shopHandle = explode('.', $shopDomainStr)[0];
                $host = base64_encode("admin.shopify.com/store/{$shopHandle}");
            } else {
                $host = urldecode($host);
            }

            // Ensure plan DB row is clean
            DB::table('plans')->where('id', $planId)->where('type', 'RECURRING')->update([
                'capped_amount' => null,
                'terms'         => null,
            ]);

            Log::info('Initiating Billing API subscription:', [
                'shop'    => $shopDomainStr,
                'plan_id' => $planId,
            ]);

            $url = null;
            $lastError = '';

            $planModel = PlanModel::firstOrCreate(
                ['id' => $planId],
                [
                    'type' => 'RECURRING',
                    'name' => 'Premium Plan',
                    'price' => 5.00,
                    'interval' => 'EVERY_30_DAYS',
                    'test' => true,
                ]
            );

            // --- Direct GraphQL Subscription Charge Creation ---
            try {
                $returnUrl = route('billing.process', [
                    'plan' => $planId,
                    'shop' => $shopDomainStr,
                    'host' => $host,
                ]);

                $gqlQuery = '
                mutation appSubscriptionCreate(
                    $name: String!,
                    $returnUrl: URL!,
                    $trialDays: Int,
                    $test: Boolean,
                    $lineItems: [AppSubscriptionLineItemInput!]!
                ) {
                    appSubscriptionCreate(
                        name: $name,
                        returnUrl: $returnUrl,
                        trialDays: $trialDays,
                        test: $test,
                        lineItems: $lineItems
                    ) {
                        appSubscription { id }
                        confirmationUrl
                        userErrors { field message }
                    }
                }';

                $gqlVariables = [
                    'name'      => $planModel->name,
                    'returnUrl' => $returnUrl,
                    'test'      => true,
                    'lineItems' => [[
                        'plan' => [
                            'appRecurringPricingDetails' => [
                                'price'    => [
                                    'amount'       => number_format((float) $planModel->price, 2, '.', ''),
                                    'currencyCode' => 'USD',
                                ],
                                'interval' => 'EVERY_30_DAYS',
                            ],
                        ],
                    ]],
                ];

                if (!empty($planModel->trial_days) && $planModel->trial_days > 0) {
                    $gqlVariables['trialDays'] = (int) $planModel->trial_days;
                }

                $gqlResponse = $shop->api()->graph($gqlQuery, $gqlVariables);
                $resArray = json_decode(json_encode($gqlResponse['body'] ?? $gqlResponse), true);

                if (!empty($resArray['data']['appSubscriptionCreate']['confirmationUrl'])) {
                    $url = $resArray['data']['appSubscriptionCreate']['confirmationUrl'];
                } else {
                    $lastError .= 'GraphQL Errors: ' . json_encode($resArray['errors'] ?? $resArray['data']['appSubscriptionCreate']['userErrors'] ?? $resArray);
                }
            } catch (\Exception $gqlEx) {
                Log::error('BillingController GraphQL exception: ' . $gqlEx->getMessage());
                $lastError .= 'GraphQL Exception: ' . $gqlEx->getMessage();
            }

            if (empty($url)) {
                Log::error('BillingController: Could not generate billing URL. Details: ' . $lastError);

                // Handle expired/invalid token after uninstall & reinstall
                if (str_contains($lastError, 'Invalid API key') || str_contains($lastError, 'access token') || str_contains($lastError, 'unrecognized login') || str_contains($lastError, 'wrong password') || str_contains($lastError, 'invalid_request') || str_contains($lastError, 'Unauthorized')) {
                    Log::warning('BillingController: Invalid token detected. Wiping dead token and initiating OAuth re-authorization for: ' . $shopDomainStr);

                    $shop->shopify_offline_refresh_token = null;
                    $shop->shopify_offline_access_token_expires_at = null;
                    $shop->shopify_offline_refresh_token_expires_at = null;
                    $shop->password = '';
                    $shop->save();

                    try {
                        $targetUrl = '/billing/' . $planId . '?' . http_build_query([
                            'plan' => $planId,
                            'shop' => $shopDomainStr,
                            'host' => $host,
                        ]);

                        session(['target' => $targetUrl]);
                        $session = new \Gnikyt\BasicShopifyAPI\Session($shopDomainStr, '');
                        $apiHelper = resolve(\Osiset\ShopifyApp\Contracts\ApiHelper::class)->make($session);
                        $authUrl = $apiHelper->buildAuthUrl(\Osiset\ShopifyApp\Objects\Enums\AuthMode::OFFLINE(), Util::getShopifyConfig('api_scopes', $shop));

                        return response()->view('shopify-app::auth.fullpage_redirect', [
                            'apiKey'     => Util::getShopifyConfig('api_key', ShopDomain::fromNative($shopDomainStr)),
                            'url'        => $authUrl,
                            'host'       => $host,
                            'shopDomain' => $shopDomainStr,
                            'locale'     => $request->get('locale'),
                        ]);
                    } catch (\Exception $authEx) {
                        Log::error('BillingController OAuth fallback failed: ' . $authEx->getMessage());
                    }
                }

                return response()->json([
                    'message' => 'Could not generate billing URL.',
                    'details' => $lastError
                ], 500);
            }

            $apiKey = Util::getShopifyConfig('api_key', ShopDomain::fromNative($shopDomainStr));
            
            // Format URL for modern Unified Admin (admin.shopify.com)
            $shopHandle = explode('.', $shopDomainStr)[0];
            $unifiedUrl = $url;
            if (str_contains($url, "{$shopDomainStr}/admin/charges/")) {
                $unifiedUrl = str_replace("{$shopDomainStr}/admin/charges/", "admin.shopify.com/store/{$shopHandle}/charges/", $url);
            } elseif (str_contains($url, 'https://') && !str_contains($url, 'admin.shopify.com') && str_contains($url, '/admin/charges/')) {
                $unifiedUrl = preg_replace('/https:\/\/[^\/]+\/admin\/charges\//', "https://admin.shopify.com/store/{$shopHandle}/charges/", $url);
            }

            Log::info('Redirecting to billing confirmation:', ['url' => $url, 'unifiedUrl' => $unifiedUrl]);

            if ($request->wantsJson() || $request->query('json')) {
                return response()->json([
                    'success' => true,
                    'confirmationUrl' => $unifiedUrl,
                    'rawUrl' => $url
                ]);
            }

            // Instant transparent redirect — top-level navigation to Unified Admin
            $safeUnifiedUrl = htmlspecialchars($unifiedUrl, ENT_QUOTES, 'UTF-8');
            $safeRawUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

            $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="shopify-api-key" content="{$apiKey}" />
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
</head>
<body style="margin:0;background:#fff;display:flex;justify-content:center;align-items:center;height:100vh;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">
    <p style="color:#5c5f62;">Redirecting to Shopify Subscription Approval... <a id="br" href="{$safeUnifiedUrl}" target="_top">Click here if not redirected automatically</a></p>
    <script type="text/javascript">
        (function() {
            var targetUrl = "{$safeUnifiedUrl}";
            var rawUrl = "{$safeRawUrl}";
            
            try {
                if (window.top && window.top !== window.self) {
                    window.top.location.href = targetUrl;
                } else {
                    window.location.href = targetUrl;
                }
            } catch(e) {
                var link = document.getElementById('br');
                if (link) {
                    link.click();
                } else {
                    window.location.href = rawUrl;
                }
            }
        })();
    </script>
</body>
</html>
HTML;

            return response($html, 200)->header('Content-Type', 'text/html');

        } catch (\Exception $e) {
            Log::error('BillingController@index fatal: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Billing error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Processes Shopify callback after merchant approves/declines billing.
     */
    public function process(
        int $plan,
        Request $request,
        ShopQuery $shopQuery
    ): RedirectResponse {
        $shopDomainStr = $request->query('shop') ?: ($request->user()?->name);

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
                $activatePlan = app(\Osiset\ShopifyApp\Actions\ActivatePlan::class);
                $activatePlan(
                    $shop->getId(),
                    PlanId::fromNative($plan),
                    ChargeReference::fromNative((int) $chargeId),
                    $host
                );
                Log::info('Plan activated successfully:', [
                    'shop'      => $shopDomainStr,
                    'plan'      => $plan,
                    'charge_id' => $chargeId,
                ]);
            } catch (\Exception $e) {
                Log::error('BillingController@process activation error: ' . $e->getMessage());
            }
        }

        return Redirect::route(Util::getShopifyConfig('route_names.home'), [
            'shop'   => $shopDomainStr,
            'host'   => $host,
            'locale' => $request->get('locale'),
        ]);
    }
}
