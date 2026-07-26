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

            // Instant transparent redirect — no visible intermediate page
            $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="shopify-api-key" content="{$apiKey}" />
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
</head>
<body style="margin:0;background:#fff;">
    <a id="br" href="{$safeUrl}" target="_top" style="display:none;"></a>
    <script>
        (function() {
            var url = "{$safeUrl}";
            try {
                if (window.top && window.top !== window.self) {
                    window.top.location.href = url;
                } else {
                    window.location.href = url;
                }
            } catch(e) {
                document.getElementById('br').click();
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
