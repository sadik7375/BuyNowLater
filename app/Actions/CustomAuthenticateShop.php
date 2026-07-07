<?php

namespace App\Actions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Actions\AfterAuthorize;
use Osiset\ShopifyApp\Actions\AuthenticateShop;
use Osiset\ShopifyApp\Actions\DispatchScripts;
use Osiset\ShopifyApp\Actions\DispatchWebhooks;
use Osiset\ShopifyApp\Actions\InstallShop;
use Osiset\ShopifyApp\Contracts\ApiHelper as IApiHelper;
use Osiset\ShopifyApp\Messaging\Events\AppInstalledEvent;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Util;

class CustomAuthenticateShop extends AuthenticateShop
{
    public function __invoke(Request $request): array
    {
        $result = call_user_func(
            $this->installShopAction,
            ShopDomain::fromNative($request->get('shop')),
            $request->query('code'),
            $request->query('id_token'),
        );

        if (! $result['completed']) {
            return [$result, false];
        }

        if ($request->has('code')) {
            $this->apiHelper->make();

            if (! $this->apiHelper->verifyRequest($request->all())) {
                return [$result, null];
            }
        }

        // Wrap post-auth actions in try-catch so webhook/script failures
        // don't crash the entire installation flow
        try {
            if (in_array($result['theme_support_level'], Util::getShopifyConfig('theme_support.unacceptable_levels'))) {
                call_user_func($this->dispatchScriptsAction, $result['shop_id'], false);
            }
        } catch (Exception $e) {
            Log::warning('CustomAuthenticateShop: Script dispatch failed (non-fatal).', [
                'message' => $e->getMessage(),
                'shop_id' => $result['shop_id'],
            ]);
        }

        try {
            call_user_func($this->dispatchWebhooksAction, $result['shop_id'], false);
        } catch (Exception $e) {
            Log::warning('CustomAuthenticateShop: Webhook dispatch failed (non-fatal).', [
                'message' => $e->getMessage(),
                'shop_id' => $result['shop_id'],
            ]);
        }

        try {
            call_user_func($this->afterAuthorizeAction, $result['shop_id']);
        } catch (Exception $e) {
            Log::warning('CustomAuthenticateShop: AfterAuthorize failed (non-fatal).', [
                'message' => $e->getMessage(),
                'shop_id' => $result['shop_id'],
            ]);
        }

        event(new AppInstalledEvent($result['shop_id']));

        return [$result, true];
    }
}
