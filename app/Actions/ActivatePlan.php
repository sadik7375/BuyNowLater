<?php

namespace App\Actions;

use Illuminate\Support\Carbon;
use Osiset\ShopifyApp\Contracts\Commands\Charge as IChargeCommand;
use Osiset\ShopifyApp\Contracts\Commands\Shop as IShopCommand;
use Osiset\ShopifyApp\Contracts\Objects\Values\PlanId;
use Osiset\ShopifyApp\Contracts\Queries\Plan as IPlanQuery;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Messaging\Events\PlanActivatedEvent;
use Osiset\ShopifyApp\Objects\Enums\ChargeStatus;
use Osiset\ShopifyApp\Objects\Enums\ChargeType;
use Osiset\ShopifyApp\Objects\Enums\PlanType;
use Osiset\ShopifyApp\Objects\Transfers\Charge as ChargeTransfer;
use Osiset\ShopifyApp\Objects\Values\ChargeId;
use Osiset\ShopifyApp\Objects\Values\ChargeReference;
use Osiset\ShopifyApp\Objects\Values\ShopId;
use Osiset\ShopifyApp\Services\ChargeHelper;
use Osiset\ShopifyApp\Actions\ActivatePlan as BaseActivatePlan;
use Illuminate\Support\Facades\Log;

class ActivatePlan extends BaseActivatePlan
{
    public function __construct(
        protected $cancelCurrentPlan,
        protected ChargeHelper $chargeHelper,
        protected IShopQuery $shopQuery,
        protected IPlanQuery $planQuery,
        protected IChargeCommand $chargeCommand,
        protected IShopCommand $shopCommand
    ) {
        parent::__construct(
            $cancelCurrentPlan,
            $chargeHelper,
            $shopQuery,
            $planQuery,
            $chargeCommand,
            $shopCommand
        );
    }

    public function __invoke(ShopId $shopId, PlanId $planId, ChargeReference $chargeRef, string $host): ChargeId
    {
        $shop = $this->shopQuery->getById($shopId);
        $plan = $this->planQuery->getById($planId);
        $chargeType = ChargeType::fromNative($plan->getType()->toNative());

        $statusStr = 'ACTIVE';

        // Try activating via REST if possible, fallback to GraphQL status check if REST fails
        try {
            $response = $shop->apiHelper()->activateCharge($chargeType, $chargeRef);
            if (isset($response['status'])) {
                $statusStr = strtoupper($response['status']);
            }
        } catch (\Exception $e) {
            Log::info("ActivatePlan: REST activateCharge failed ({$e->getMessage()}), checking subscription status via GraphQL.");
            try {
                $gqlId = "gid://shopify/AppSubscription/" . $chargeRef->toNative();
                $gqlQuery = 'query getSub($id: ID!) { node(id: $id) { ... on AppSubscription { status } } }';
                $gqlRes = $shop->api()->graph($gqlQuery, ['id' => $gqlId]);
                $nodeStatus = $gqlRes['body']['data']['node']['status'] ?? null;
                if ($nodeStatus) {
                    $statusStr = strtoupper($nodeStatus);
                }
            } catch (\Exception $gqlEx) {
                Log::warning("ActivatePlan: GraphQL status fallback error: " . $gqlEx->getMessage());
            }
        }

        // Cancel the shop's current plan
        try {
            call_user_func($this->cancelCurrentPlan, $shopId);
        } catch (\Exception $e) {
            Log::warning("ActivatePlan: Error cancelling previous plan: " . $e->getMessage());
        }

        // Delete existing charge record if present
        try {
            $this->chargeCommand->delete($chargeRef, $shopId);
        } catch (\Exception $e) {
            // Ignore if not found
        }

        $transfer = new ChargeTransfer();
        $transfer->shopId = $shopId;
        $transfer->planId = $planId;
        $transfer->chargeReference = $chargeRef;
        $transfer->chargeType = $chargeType;
        $transfer->chargeStatus = ChargeStatus::fromNative($statusStr);
        $transfer->planDetails = $this->chargeHelper->details($plan, $shop, $host);

        if ($plan->isType(PlanType::RECURRING())) {
            $transfer->activatedOn = Carbon::now();
            $transfer->billingOn = Carbon::now()->addDays(30);
            $transfer->trialEndsOn = null;
        } else {
            $transfer->activatedOn = Carbon::today();
            $transfer->billingOn = null;
            $transfer->trialEndsOn = null;
        }

        $charge = $this->chargeCommand->make($transfer);
        $this->shopCommand->setToPlan($shopId, $planId);

        event(new PlanActivatedEvent($shop, $plan, $charge));

        return $charge;
    }
}
