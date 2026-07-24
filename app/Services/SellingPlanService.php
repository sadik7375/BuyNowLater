<?php

namespace App\Services;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class SellingPlanService
{
    /**
     * Create or update a Shopify Selling Plan Group for the merchant's store.
     *
     * @param User $shop
     * @param int $depositPercentage
     * @param int $holdDurationDays
     * @return array|null Returns ['group_id' => ..., 'plan_id' => ...] or null on failure
     */
    public function createOrUpdatePlanGroup(User $shop, int $depositPercentage = 10, int $holdDurationDays = 14): ?array
    {
        $settings = Setting::where('shop_id', $shop->id)->first();
        $existingGroupId = $settings ? $settings->selling_plan_group_id : null;

        $planName = "{$depositPercentage}% Deposit — Pay Remaining Later";
        $groupName = "Buy Now Later ({$depositPercentage}% Deposit)";

        if ($existingGroupId) {
            // Delete existing group first to ensure stale policies or missing plan IDs are cleanly purged
            $this->deletePlanGroup($shop, $existingGroupId);
        }

        // Create new Selling Plan Group
        $mutation = '
        mutation sellingPlanGroupCreate($input: SellingPlanGroupInput!) {
            sellingPlanGroupCreate(input: $input) {
                sellingPlanGroup {
                    id
                    name
                    sellingPlans(first: 5) {
                        edges {
                            node {
                                id
                                name
                            }
                        }
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        ';

        $variables = [
            'input' => [
                'name' => $groupName,
                'merchantCode' => 'buylater-deposit-' . $depositPercentage,
                'options' => ['Deposit Percentage'],
                'position' => 1,
                'sellingPlansToCreate' => [
                    [
                        'name' => $planName,
                        'options' => ["{$depositPercentage}% Deposit"],
                        'category' => 'OTHER',
                        'billingPolicy' => [
                            'fixed' => [
                                'checkoutCharge' => [
                                    'type' => 'PERCENTAGE',
                                    'value' => [
                                        'percentage' => (float) $depositPercentage,
                                    ],
                                ],
                                'remainingBalanceChargeTrigger' => 'NO_TRIGGER',
                            ],
                        ],
                        'deliveryPolicy' => [
                            'fixed' => [
                                'fulfillmentTrigger' => 'UNKNOWN',
                            ],
                        ],
                        'pricingPolicies' => [
                            [
                                'fixed' => [
                                    'adjustmentType' => 'PERCENTAGE',
                                    'adjustmentValue' => [
                                        'percentage' => (float) $depositPercentage,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            Log::info("SellingPlanService: Creating SellingPlanGroup for shop {$shop->name}", $variables);
            $response = $shop->api()->graph($mutation, $variables);

            if ($response['errors'] === false && isset($response['body']['data']['sellingPlanGroupCreate']['sellingPlanGroup'])) {
                $group = $response['body']['data']['sellingPlanGroupCreate']['sellingPlanGroup'];
                $groupId = $group['id'] ?? null;
                $plans = $group['sellingPlans']['edges'] ?? [];
                $rawPlanId = $plans[0]['node']['id'] ?? null;
                $planId = $rawPlanId;
                if ($rawPlanId && preg_match('/SellingPlan\/(\d+)/', $rawPlanId, $m)) {
                    $planId = $m[1];
                }

                if ($groupId && $settings) {
                    $settings->update([
                        'selling_plan_group_id' => $groupId,
                        'selling_plan_id' => $planId,
                        'use_selling_plan' => true,
                    ]);
                }

                Log::info("SellingPlanService: Successfully created SellingPlanGroup {$groupId} with plan {$planId}");
                return [
                    'group_id' => $groupId,
                    'plan_id' => $rawPlanId,
                ];
            } else {
                $userErrors = $response['body']['data']['sellingPlanGroupCreate']['userErrors'] ?? [];
                Log::error("SellingPlanService: Failed to create SellingPlanGroup", [
                    'errors' => $response['errors'],
                    'userErrors' => $userErrors,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("SellingPlanService: Exception creating SellingPlanGroup: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Update an existing Selling Plan Group.
     */
    protected function updatePlanGroup(User $shop, string $groupId, int $depositPercentage, int $holdDurationDays, string $planName, string $groupName): ?array
    {
        $mutation = '
        mutation sellingPlanGroupUpdate($id: ID!, $input: SellingPlanGroupInput!) {
            sellingPlanGroupUpdate(id: $id, input: $input) {
                sellingPlanGroup {
                    id
                    name
                    sellingPlans(first: 5) {
                        edges {
                            node {
                                id
                                name
                            }
                        }
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        ';

        $variables = [
            'id' => $groupId,
            'input' => [
                'name' => $groupName,
                'merchantCode' => 'buylater-deposit-' . $depositPercentage,
            ],
        ];

        try {
            $response = $shop->api()->graph($mutation, $variables);
            if ($response['errors'] === false && isset($response['body']['data']['sellingPlanGroupUpdate']['sellingPlanGroup'])) {
                $group = $response['body']['data']['sellingPlanGroupUpdate']['sellingPlanGroup'];
                $plans = $group['sellingPlans']['edges'] ?? [];
                $planId = $plans[0]['node']['id'] ?? null;
                return [
                    'group_id' => $group['id'] ?? $groupId,
                    'plan_id' => $planId,
                ];
            }
        } catch (\Exception $e) {
            Log::error("SellingPlanService: Exception updating SellingPlanGroup: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Attach products to a Selling Plan Group.
     *
     * @param User $shop
     * @param string $groupId
     * @param array $productGqlIds Array of GraphQL Product IDs e.g. ["gid://shopify/Product/12345678"]
     * @return bool
     */
    public function attachProducts(User $shop, string $groupId, array $productGqlIds): bool
    {
        if (empty($productGqlIds)) {
            return true;
        }

        $mutation = '
        mutation sellingPlanGroupAddProducts($id: ID!, $productIds: [ID!]!) {
            sellingPlanGroupAddProducts(id: $id, productIds: $productIds) {
                sellingPlanGroup {
                    id
                }
                userErrors {
                    field
                    message
                }
            }
        }
        ';

        try {
            $response = $shop->api()->graph($mutation, [
                'id' => $groupId,
                'productIds' => $productGqlIds,
            ]);

            if ($response['errors'] === false && isset($response['body']['data']['sellingPlanGroupAddProducts']['sellingPlanGroup'])) {
                Log::info("SellingPlanService: Attached " . count($productGqlIds) . " products to SellingPlanGroup {$groupId}");
                return true;
            } else {
                Log::error("SellingPlanService: Failed to attach products", [
                    'userErrors' => $response['body']['data']['sellingPlanGroupAddProducts']['userErrors'] ?? []
                ]);
            }
        } catch (\Exception $e) {
            Log::error("SellingPlanService: Exception attaching products: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Detach products from a Selling Plan Group.
     *
     * @param User $shop
     * @param string $groupId
     * @param array $productGqlIds
     * @return bool
     */
    public function detachProducts(User $shop, string $groupId, array $productGqlIds): bool
    {
        if (empty($productGqlIds)) {
            return true;
        }

        $mutation = '
        mutation sellingPlanGroupRemoveProducts($id: ID!, $productIds: [ID!]!) {
            sellingPlanGroupRemoveProducts(id: $id, productIds: $productIds) {
                removedProductIds
                userErrors {
                    field
                    message
                }
            }
        }
        ';

        try {
            $response = $shop->api()->graph($mutation, [
                'id' => $groupId,
                'productIds' => $productGqlIds,
            ]);

            if ($response['errors'] === false) {
                Log::info("SellingPlanService: Detached products from SellingPlanGroup {$groupId}");
                return true;
            }
        } catch (\Exception $e) {
            Log::error("SellingPlanService: Exception detaching products: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Delete a Selling Plan Group.
     *
     * @param User $shop
     * @param string $groupId
     * @return bool
     */
    public function deletePlanGroup(User $shop, string $groupId): bool
    {
        $mutation = '
        mutation sellingPlanGroupDelete($id: ID!) {
            sellingPlanGroupDelete(id: $id) {
                deletedSellingPlanGroupId
                userErrors {
                    field
                    message
                }
            }
        }
        ';

        try {
            $response = $shop->api()->graph($mutation, ['id' => $groupId]);

            if ($response['errors'] === false) {
                $settings = Setting::where('shop_id', $shop->id)->first();
                if ($settings) {
                    $settings->update([
                        'selling_plan_group_id' => null,
                        'use_selling_plan' => false,
                    ]);
                }
                Log::info("SellingPlanService: Deleted SellingPlanGroup {$groupId}");
                return true;
            }
        } catch (\Exception $e) {
            Log::error("SellingPlanService: Exception deleting SellingPlanGroup: " . $e->getMessage());
        }

        return false;
    }
}
