<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingRedirectConfigTest extends TestCase
{
    #[Test]
    public function billing_redirect_urls_include_the_subscription_return_route(): void
    {
        $files = [
            base_path('shopify.app.toml'),
            base_path('shopify.app.buy-now-later.toml'),
        ];

        foreach ($files as $file) {
            $this->assertFileExists($file);

            $contents = file_get_contents($file);
            $this->assertNotFalse($contents);

            $this->assertStringContainsString('/billing/process/1', $contents);
        }
    }
}
