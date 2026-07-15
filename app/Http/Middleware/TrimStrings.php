<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function handle($request, \Closure $next)
    {
        if (str_contains($request->getRequestUri(), 'webhook')) {
            \Illuminate\Support\Facades\Log::info('Webhook incoming request details:', [
                'uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
                'content' => $request->getContent()
            ]);
        }

        return parent::handle($request, $next);
    }
}
