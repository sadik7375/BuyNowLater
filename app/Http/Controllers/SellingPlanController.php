<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\SellingPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SellingPlanController extends Controller
{
    protected SellingPlanService $sellingPlanService;

    public function __construct(SellingPlanService $sellingPlanService)
    {
        $this->sellingPlanService = $sellingPlanService;
    }

    /**
     * Activate and setup Selling Plan Group for the shop.
     */
    public function setup(Request $request)
    {
        $shop = auth()->user();
        if (!$shop) {
            return redirect()->back()->with('error', 'Unauthorized shop session.');
        }

        $settings = Setting::firstOrCreate(
            ['shop_id' => $shop->id],
            [
                'deposit_percentage' => 10,
                'hold_duration_days' => 14,
            ]
        );

        $depositPercentage = (int) ($settings->deposit_percentage ?? 10);
        $holdDurationDays = (int) ($settings->hold_duration_days ?? 14);

        // 1. Create or update Selling Plan Group via GraphQL
        $result = $this->sellingPlanService->createOrUpdatePlanGroup($shop, $depositPercentage, $holdDurationDays);

        if (!$result || empty($result['group_id'])) {
            return redirect()->back()->with('error', 'Failed to create Shopify Selling Plan Group. Please check API scopes.');
        }

        $groupId = $result['group_id'];

        // 2. Fetch products and attach them to the Selling Plan Group
        $productGqlIds = [];
        try {
            $gqlQuery = 'query getProducts($first: Int!) {
                products(first: $first) {
                    edges {
                        node {
                            id
                        }
                    }
                }
            }';

            $response = $shop->api()->graph($gqlQuery, ['first' => 250]);
            if ($response['errors'] === false && isset($response['body']['data']['products']['edges'])) {
                foreach ($response['body']['data']['products']['edges'] as $edge) {
                    if (isset($edge['node']['id'])) {
                        $productGqlIds[] = $edge['node']['id'];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("SellingPlanController: Failed to fetch products for auto-attachment: " . $e->getMessage());
        }

        if (!empty($productGqlIds)) {
            $this->sellingPlanService->attachProducts($shop, $groupId, $productGqlIds);
        }

        $settings->update([
            'selling_plan_group_id' => $groupId,
            'use_selling_plan' => true,
        ]);

        return redirect()->back()->with('success', 'Native Checkout (Selling Plan API) activated successfully and products linked!');
    }

    /**
     * Deactivate and delete Selling Plan Group.
     */
    public function destroy(Request $request)
    {
        $shop = auth()->user();
        if (!$shop) {
            return redirect()->back()->with('error', 'Unauthorized shop session.');
        }

        $settings = Setting::where('shop_id', $shop->id)->first();
        if ($settings && $settings->selling_plan_group_id) {
            $this->sellingPlanService->deletePlanGroup($shop, $settings->selling_plan_group_id);
        }

        if ($settings) {
            $settings->update([
                'selling_plan_group_id' => null,
                'use_selling_plan' => false,
            ]);
        }

        return redirect()->back()->with('success', 'Native Checkout deactivated. App will use Standard Draft Order mode.');
    }
}
