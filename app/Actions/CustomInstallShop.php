<?php

namespace App\Actions;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Actions\InstallShop as BaseInstallShop;
use Osiset\ShopifyApp\Objects\Enums\AuthMode;
use Osiset\ShopifyApp\Objects\Enums\ThemeSupportLevel as ThemeSupportLevelEnum;
use Osiset\ShopifyApp\Objects\Values\AccessToken;
use Osiset\ShopifyApp\Objects\Values\NullAccessToken;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Objects\Values\ThemeSupportLevel;
use Osiset\ShopifyApp\Util;

class CustomInstallShop extends BaseInstallShop
{
    public function __invoke(ShopDomain $shopDomain, ?string $code = null, ?string $idToken = null): array
    {
        Log::info('CustomInstallShop: Initiating authentication/installation.', [
            'shop' => $shopDomain->toNative(),
            'has_code' => !empty($code),
            'has_idToken' => !empty($idToken)
        ]);

        $shop = $this->shopQuery->getByDomain($shopDomain, [], true);

        if ($shop === null) {
            $this->shopCommand->make($shopDomain, NullAccessToken::fromNative(null));
            $shop = $this->shopQuery->getByDomain($shopDomain);
        }

        try {
            if (!$shop->hasOfflineAccess()) {
                $session = new \Gnikyt\BasicShopifyAPI\Session($shopDomain->toNative(), '');
                $apiHelper = resolve(\Osiset\ShopifyApp\Contracts\ApiHelper::class)->make($session);
            } else {
                $apiHelper = $shop->apiHelper();
            }
        } catch (\Throwable $ex) {
            Log::warning('CustomInstallShop: apiHelper failed due to invalid/expired token. Wiping stale tokens to allow re-auth.', [
                'shop' => $shopDomain->toNative(),
                'error' => $ex->getMessage()
            ]);
            
            $shop->shopify_offline_refresh_token = null;
            $shop->shopify_offline_access_token_expires_at = null;
            $shop->shopify_offline_refresh_token_expires_at = null;
            $shop->password = '';
            $shop->save();
            
            $session = new \Gnikyt\BasicShopifyAPI\Session($shopDomain->toNative(), '');
            $apiHelper = resolve(\Osiset\ShopifyApp\Contracts\ApiHelper::class)->make($session);
        }

        $grantMode = $shop->hasOfflineAccess()
            ? AuthMode::fromNative(Util::getShopifyConfig('api_grant_mode', $shop))
            : AuthMode::OFFLINE();

        if (empty($code) && empty($idToken)) {
            return [
                'completed' => false,
                'url' => $apiHelper->buildAuthUrl($grantMode, Util::getShopifyConfig('api_scopes', $shop)),
                'shop_id' => $shop->getId(),
            ];
        }

        try {
            if ($shop->trashed()) {
                $shop->restore();
                $shop->plan_id = null;
                $shop->shopify_freemium = 0;
                $shop->save();
            }

            // Get the data and set the access token
            $data = $idToken !== null
                ? $apiHelper->performOfflineTokenExchange($idToken)
                : $apiHelper->getAccessData($code, $grantMode);
            $this->persistShopifyOAuthTokens($shop, $data, $grantMode);

            // On fresh install / reinstall, Shopify cancels previous subscriptions, so reset plan to free
            $shop->plan_id = null;
            $shop->shopify_freemium = 0;
            $shop->save();

            try {
                $themeSupportLevel = call_user_func($this->verifyThemeSupport, $shop->getId());
                $this->shopCommand->setThemeSupportLevel($shop->getId(), ThemeSupportLevel::fromNative($themeSupportLevel));
            } catch (Exception $e) {
                $themeSupportLevel = ThemeSupportLevelEnum::NONE;
            }

            return [
                'completed' => true,
                'url' => null,
                'shop_id' => $shop->getId(),
                'theme_support_level' => $themeSupportLevel,
            ];
        } catch (Exception $e) {
            Log::error('CustomInstallShop Exception: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                try {
                    $body = $e->getResponse()->getBody();
                    $body->rewind();
                    Log::error('CustomInstallShop Shopify Response: ' . $body->getContents());
                } catch (Exception $logEx) {
                    Log::error('CustomInstallShop: Failed to get response body: ' . $logEx->getMessage());
                }
            }

            return [
                'completed' => false,
                'url' => null,
                'shop_id' => null,
                'theme_support_level' => null,
            ];
        }
    }
}
