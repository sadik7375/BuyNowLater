<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shop = $request->get('shop') ?: ($request->user() ? $request->user()->name : null);
        $host = $request->get('host') ?: ($shop ? base64_encode("admin.shopify.com/store/" . explode('.', $shop)[0]) : null);

        return array_merge(parent::share($request), [
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'status' => fn () => $request->session()->get('status'),
            ],
            'shopify' => [
                'api_key' => config('shopify-app.api_key'),
                'host' => $host,
                'shop' => $shop,
            ],
            'host' => $host,
            'shop' => $shop,
        ]);
    }
}
