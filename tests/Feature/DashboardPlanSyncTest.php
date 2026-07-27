<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardPlanSyncTest extends TestCase
{
    #[Test]
    public function reinstall_does_not_reactivate_paid_plan_from_stale_shopify_subscription(): void
    {
        $shop = new \stdClass();
        $shop->id = 42;
        $shop->plan_id = null;

        $controller = new DashboardController();
        $method = new \ReflectionMethod($controller, 'shouldTreatShopAsPaid');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $shop, [['status' => 'ACTIVE']], false);

        $this->assertFalse($result);
    }

    #[Test]
    public function existing_paid_shop_with_local_active_charge_stays_paid(): void
    {
        $shop = new \stdClass();
        $shop->id = 42;
        $shop->plan_id = 1;

        $controller = new DashboardController();
        $method = new \ReflectionMethod($controller, 'shouldTreatShopAsPaid');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $shop, [], true);

        $this->assertTrue($result);
    }
}
