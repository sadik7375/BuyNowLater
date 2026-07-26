<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Osiset\ShopifyApp\Actions\ActivatePlan;
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

            // --- GraphQL appSubscriptionCreate ---
            try {
                $planModel = PlanModel::findOrFail($planId);
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
                                    'amount'       => (float) $planModel->price,
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

                $gqlResponse = $shop->apiHelper()->getApi()->graph($gqlQuery, $gqlVariables);
                $subData = $gqlResponse['body']['data']['appSubscriptionCreate'] ?? [];

                if (!empty($subData['confirmationUrl'])) {
                    $url = $subData['confirmationUrl'];
                    Log::info('Got confirmationUrl:', ['url' => $url]);
                } else {
                    $errors = $subData['userErrors'] ?? [];
                    Log::error('GraphQL userErrors:', json_decode(json_encode($errors), true));
                }
            } catch (\Exception $gqlEx) {
                Log::error('GraphQL appSubscriptionCreate exception: ' . $gqlEx->getMessage());
            }

            // --- Fallback to package helper ---
            if (empty($url)) {
                try {
                    $url = $getPlanUrl(
                        $shop->getId(),
                        NullablePlanId::fromNative($planId),
                        $host
                    );
                    Log::info('Got URL from GetPlanUrl fallback:', ['url' => $url]);
                } catch (\Exception $ex) {
                    Log::error('GetPlanUrl fallback failed: ' . $ex->getMessage());
                }
            }

            if (empty($url)) {
                Log::error('BillingController: No URL could be generated.');
                return response()->json(['message' => 'Could not generate billing URL.'], 500);
            }

            $apiKey = Util::getShopifyConfig('api_key', ShopDomain::fromNative($shopDomainStr));

            Log::info('Redirecting to billing confirmation:', ['url' => $url]);

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
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f6f6f7; color: #202223; }
        .spinner { width: 44px; height: 44px; border: 4px solid #e1e3e5; border-top: 4px solid #008060; border-radius: 50%; animation: spin 0.9s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
        h3 { margin: 0 0 8px 0; font-size: 18px; }
        p { margin: 0 0 20px 0; color: #6d7175; font-size: 14px; }
        .btn { padding: 12px 28px; background: #008060; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; display: inline-block; }
        .btn:hover { background: #006e52; }
    </style>
</head>
<body>
    <div class="spinner"></div>
    <h3>Redirecting to Approve Subscription...</h3>
    <p>You'll be taken to Shopify's secure billing approval page.</p>
    <a href="{$url}" target="_top" class="btn">Approve Premium Plan ($5/mo) &rarr;</a>
    <script>
        (function () {
            var url = "{$url}";
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
                // Resolve ActivatePlan through the service container (it has a callable dependency)
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
