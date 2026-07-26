<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;
use Osiset\ShopifyApp\Actions\ActivatePlan;
use Osiset\ShopifyApp\Actions\GetPlanUrl;
use Osiset\ShopifyApp\Objects\Values\ChargeReference;
use Osiset\ShopifyApp\Objects\Values\NullablePlanId;
use Osiset\ShopifyApp\Objects\Values\PlanId;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
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
            // Get shop domain from request query, input, or authenticated user
            $shopDomainStr = $request->get('shop') ?: $request->query('shop');
            if (empty($shopDomainStr) && auth()->check()) {
                $shopDomainStr = auth()->user()->name;
            }

            if (empty($shopDomainStr)) {
                Log::error('Billing index error: Shop domain could not be resolved from request or auth.');
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

            Log::info('Initiating billing subscription request:', [
                'shop' => $shopDomainStr,
                'plan_id' => $planId,
                'host' => $host
            ]);

            // Get the plan URL for redirect
            $url = $getPlanUrl(
                $shop->getId(),
                NullablePlanId::fromNative($planId),
                $host
            );

            // Return fullpage redirect view
            return View::make(
                'shopify-app::billing.fullpage_redirect',
                [
                    'url' => $url,
                    'host' => $host,
                    'locale' => $request->get('locale'),
                    'apiKey' => Util::getShopifyConfig('api_key', ShopDomain::fromNative($shopDomainStr)),
                ]
            );
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
        $host = urldecode($request->get('host'));

        if (!$request->has('charge_id')) {
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
                ChargeReference::fromNative((int) $request->query('charge_id')),
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
