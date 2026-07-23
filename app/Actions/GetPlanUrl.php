<?php

namespace App\Actions;

use Osiset\ShopifyApp\Contracts\Queries\Plan as IPlanQuery;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Objects\Values\NullablePlanId;
use Osiset\ShopifyApp\Objects\Values\ShopId;
use Osiset\ShopifyApp\Services\ChargeHelper;
use Osiset\ShopifyApp\Actions\GetPlanUrl as BaseGetPlanUrl;

class GetPlanUrl extends BaseGetPlanUrl
{
    public function __construct(
        protected ChargeHelper $chargeHelper,
        protected IPlanQuery $planQuery,
        protected IShopQuery $shopQuery
    ) {
        parent::__construct($chargeHelper, $planQuery, $shopQuery);
    }

    /**
     * Always use GraphQL appSubscriptionCreate to generate subscription URL.
     * Prevents REST deprecation errors and "Unknown error" screens.
     */
    public function __invoke(ShopId $shopId, NullablePlanId $planId, string $host): string
    {
        $shop = $this->shopQuery->getById($shopId);
        $plan = $planId->isNull() ? $this->planQuery->getDefault() : $this->planQuery->getById($planId);

        $api = $shop->apiHelper()
            ->createChargeGraphQL($this->chargeHelper->details($plan, $shop, $host));

        if (isset($api['confirmationUrl']) && !empty($api['confirmationUrl'])) {
            return $api['confirmationUrl'];
        }

        // Fallback if confirmationUrl is nested differently in response
        return $api['body']['data']['appSubscriptionCreate']['confirmationUrl'] ?? '';
    }
}
