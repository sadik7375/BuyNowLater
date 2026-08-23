<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

if (!class_exists(\Inertia\Middleware::class)) {
    class HandleInertiaRequests
    {
        public function handle(Request $request, \Closure $next)
        {
            return $next($request);
        }
    }
} else {
    class HandleInertiaRequests extends \Inertia\Middleware
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
            return array_merge(parent::share($request), [
                'flash' => [
                    'success' => fn () => $request->session()->get('success'),
                    'error' => fn () => $request->session()->get('error'),
                    'warning' => fn () => $request->session()->get('warning'),
                    'status' => fn () => $request->session()->get('status'),
                ],
                'shopify' => [
                    'api_key' => config('shopify-app.api_key'),
                    'host' => $request->get('host'),
                    'shop' => $request->get('shop'),
                ],
            ]);
        }
    }
}
