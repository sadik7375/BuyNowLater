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
     * Redirects to billing screen for Shopify.
     */
    public function index(
        Request $request,
        ShopQuery $shopQuery,
        GetPlanUrl $getPlanUrl,
        ?int $plan = 1
    ) {
        try {
            // 1. Direct shop parameter in query or input
            $shopDomainStr = $request->get('shop') ?: $request->query('shop') ?: $request->input('shop');

            // 2. Extract shop domain from JWT session token (id_token or token query param / header)
            if (empty($shopDomainStr)) {
                $tokenString = $request->get('id_token') ?: $request->get('token') ?: $request->header('Authorization');
                if ($tokenString) {
                    $tokenString = str_replace('Bearer ', '', $tokenString);
                    try {
                        $sessionToken = new SessionToken($tokenString, false);
                        $shopDomainStr = $sessionToken->getShopDomain()->toNative();
                    } catch (\Exception $e) {
                        Log::warning('BillingController: Could not parse SessionToken for shop domain: ' . $e->getMessage());
                    }
                }
            }

            // 3. Fallback to authenticated user (if logged in)
            if (empty($shopDomainStr) && auth()->check()) {
                $shopDomainStr = auth()->user()->name;
            }

            // 4. Fallback to extracting store handle from referer header
            if (empty($shopDomainStr)) {
                $referer = $request->header('referer') ?: $request->header('referrer');
                if ($referer && preg_match('/store\/([a-zA-Z0-9\-]+)/', $referer, $matches)) {
                    $shopDomainStr = $matches[1] . '.myshopify.com';
                }
            }

            // 5. Ultimate fallback: single shop record in database
            if (empty($shopDomainStr)) {
                $firstUser = User::first();
                if ($firstUser) {
                    $shopDomainStr = $firstUser->name;
                }
            }

            if (empty($shopDomainStr)) {
                Log::error('Billing index error: Shop domain could not be resolved from request, JWT token, or DB.');
                return response()->json(['message' => 'Shop domain missing.'], 400);
            }

            $shop = $shopQuery->getByDomain(ShopDomain::fromNative($shopDomainStr));

            // Determine host parameter
            $host = $request->get('host');
            if (empty($host)) {
                $shopHandle = explode('.', $shopDomainStr)[0];
                $host = base64_encode("admin.shopify.com/store/{$shopHandle}");
            } else {
                $host = urldecode($host);
            }

            $planId = $plan ?: 1;

            // Ensure plan record in DB has null capped_amount and terms for pure RECURRING plan
            DB::table('plans')->where('id', $planId)->where('type', 'RECURRING')->update([
                'capped_amount' => null,
                'terms' => null,
            ]);

            Log::info('Initiating billing subscription request:', [
                'shop' => $shopDomainStr,
                'plan_id' => $planId,
                'host' => $host
            ]);

            $url = null;

            // Execute official Shopify GraphQL appSubscriptionCreate mutation according to shopify.dev guidelines
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
                        appSubscription {
                            id
                        }
                        confirmationUrl
                        userErrors {
                            field
                            message
                        }
                    }
                }
                ';

                $gqlVariables = [
                    'name' => $planModel->name,
                    'returnUrl' => $returnUrl,
                    'test' => (bool) ($planModel->test ?? true),
                    'lineItems' => [
                        [
                            'plan' => [
                                'appRecurringPricingDetails' => [
                                    'price' => [
                                        'amount' => (float) $planModel->price,
                                        'currencyCode' => 'USD',
                                    ],
                                    'interval' => 'EVERY_30_DAYS',
                                ],
                            ],
                        ],
                    ],
                ];

                if (!empty($planModel->trial_days) && $planModel->trial_days > 0) {
                    $gqlVariables['trialDays'] = (int) $planModel->trial_days;
                }

                $gqlResponse = $shop->apiHelper()->getApi()->graph($gqlQuery, $gqlVariables);
                $subData = $gqlResponse['body']['data']['appSubscriptionCreate'] ?? [];

                if (!empty($subData['confirmationUrl'])) {
                    $url = $subData['confirmationUrl'];
                } else {
                    $userErrors = $subData['userErrors'] ?? [];
                    Log::warning('Shopify Billing GraphQL userErrors:', json_decode(json_encode($userErrors), true));
                }
            } catch (\Exception $gqlEx) {
                Log::warning('GraphQL appSubscriptionCreate failed, falling back to GetPlanUrl: ' . $gqlEx->getMessage());
            }

            // Fallback to GetPlanUrl if GraphQL returned empty confirmation URL
            if (empty($url)) {
                $url = $getPlanUrl(
                    $shop->getId(),
                    NullablePlanId::fromNative($planId),
                    $host
                );
            }

            Log::info('Generated billing confirmation URL:', ['url' => $url]);

            $apiKey = Util::getShopifyConfig('api_key', ShopDomain::fromNative($shopDomainStr));

            // Return fullpage redirect HTML to break out of embedded iframe
            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="shopify-api-key" content="{$apiKey}" />
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <title>Redirecting to Shopify Billing...</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Segoe UI", Roboto, Helvetica, Arial, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f6f6f7; color: #202223; }
        .spinner { width: 40px; height: 40px; border: 4px solid #e1e3e5; border-top: 4px solid #008060; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .redirect-btn { margin-top: 15px; padding: 12px 24px; background: #008060; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block; font-size: 14px; }
        .redirect-btn:hover { background: #006e52; }
    </style>
</head>
<body>
    <div class="spinner"></div>
    <h3 style="margin: 0 0 10px 0;">Redirecting to Shopify Payment Approval...</h3>
    <p style="margin: 0 0 15px 0; color: #6d7175;">If you are not redirected automatically, click the button below:</p>
    <a id="redirect-link" href="{$url}" target="_top" class="redirect-btn">Approve Premium Plan ($5/mo) &rarr;</a>

    <script type="text/javascript">
        (function() {
            var redirectUrl = "{$url}";
            try {
                if (window.top && window.top !== window.self) {
                    window.top.location.href = redirectUrl;
                } else {
                    window.location.href = redirectUrl;
                }
            } catch(e) {
                try {
                    window.open(redirectUrl, '_top');
                } catch(err) {}
            }
        })();
    </script>
</body>
</html>
HTML;

            return response($html, 200)->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            Log::error('Billing index exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Billing failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Processes the response from the customer after approving or declining billing.
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
            $host = urldecode($host);
        }

        $chargeId = $request->query('charge_id') ?: $request->query('charge_id');

        if (!$chargeId) {
            Log::warning('Billing process: charge_id is missing in response.', [
                'shop' => $shopDomainStr
            ]);
            return Redirect::route(Util::getShopifyConfig('route_names.home'), [
                'shop' => $shopDomainStr,
                'host' => $host,
                'locale' => $request->get('locale'),
            ]);
        }

        try {
            $shop = $shopQuery->getByDomain(ShopDomain::fromNative($shopDomainStr));

            $activatePlan(
                $shop->getId(),
                PlanId::fromNative($plan),
                ChargeReference::fromNative((int) $chargeId),
                $host
            );

            Log::info('Billing successfully activated for shop:', [
                'shop' => $shopDomainStr,
                'plan_id' => $plan
            ]);

            return Redirect::route(Util::getShopifyConfig('route_names.home'), [
                'shop' => $shop->getDomain()->toNative(),
                'host' => $host,
                'locale' => $request->get('locale'),
            ]);
        } catch (\Exception $e) {
            Log::error('Billing process activation error: ' . $e->getMessage());
            return Redirect::route(Util::getShopifyConfig('route_names.home'), [
                'shop' => $shopDomainStr,
                'host' => $host,
                'locale' => $request->get('locale'),
            ]);
        }
    }
}
