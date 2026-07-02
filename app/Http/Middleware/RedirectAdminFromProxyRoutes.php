<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminFromProxyRoutes
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the request is not from the storefront proxy (lacks path_prefix),
        // redirect to the home route while keeping settings tab.
        if (!$request->has('path_prefix')) {
            return redirect()->to(route('home', $request->query()) . '#settings');
        }

        return $next($request);
    }
}
