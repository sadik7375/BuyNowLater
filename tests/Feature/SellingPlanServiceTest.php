<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Setting;
use App\Services\SellingPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class SellingPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_plan_group_sends_graphql_and_saves_settings()
    {
        $user = User::factory()->create([
            'name' => 'test-selling-plan.myshopify.com',
            'password' => 'token123',
        ]);

        $settings = Setting::create([
            'shop_id' => $user->id,
            'deposit_percentage' => 15,
            'hold_duration_days' => 14,
        ]);

        $apiMock = Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);
        $apiMock->shouldReceive('graph')
            ->andReturnUsing(function($query, $vars = []) {
                if (str_contains($query, 'sellingPlanGroupCreate')) {
                    return [
                        'errors' => false,
                        'body' => [
                            'data' => [
                                'sellingPlanGroupCreate' => [
                                    'sellingPlanGroup' => [
                                        'id' => 'gid://shopify/SellingPlanGroup/1001',
                                        'name' => 'Buy Now Later (15% Deposit)',
                                        'sellingPlans' => [
                                            'edges' => [
                                                [
                                                    'node' => [
                                                        'id' => 'gid://shopify/SellingPlan/2001',
                                                        'name' => '15% Deposit — Pay Remaining Later',
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                    'userErrors' => []
                                ]
                            ]
                        ]
                    ];
                }
                return [
                    'errors' => false,
                    'body' => [
                        'data' => [
                            'shop' => ['id' => 'gid://shopify/Shop/123'],
                            'metafieldsSet' => ['metafields' => [['id' => '1']], 'userErrors' => []]
                        ]
                    ]
                ];
            });

        $userMock = Mockery::mock($user)->makePartial();
        $userMock->shouldReceive('api')->andReturn($apiMock);

        $service = new SellingPlanService();
        $result = $service->createOrUpdatePlanGroup($userMock, 15, 14);

        $this->assertNotNull($result);
        $this->assertEquals('gid://shopify/SellingPlanGroup/1001', $result['group_id']);
        $this->assertEquals('gid://shopify/SellingPlan/2001', $result['plan_id']);

        $settings->refresh();
        $this->assertEquals('gid://shopify/SellingPlanGroup/1001', $settings->selling_plan_group_id);
        $this->assertTrue((bool)$settings->use_selling_plan);
    }

    public function test_attach_products_sends_graphql()
    {
        $user = User::factory()->create([
            'name' => 'test-selling-plan-attach.myshopify.com',
            'password' => 'token123',
        ]);

        $apiMock = Mockery::mock(\Gnikyt\BasicShopifyAPI\BasicShopifyAPI::class);
        $apiMock->shouldReceive('graph')
            ->once()
            ->andReturn([
                'errors' => false,
                'body' => [
                    'data' => [
                        'sellingPlanGroupAddProducts' => [
                            'sellingPlanGroup' => [
                                'id' => 'gid://shopify/SellingPlanGroup/1001'
                            ],
                            'userErrors' => []
                        ]
                    ]
                ]
            ]);

        $userMock = Mockery::mock($user)->makePartial();
        $userMock->shouldReceive('api')->andReturn($apiMock);

        $service = new SellingPlanService();
        $success = $service->attachProducts($userMock, 'gid://shopify/SellingPlanGroup/1001', ['gid://shopify/Product/12345']);

        $this->assertTrue($success);
    }
}
