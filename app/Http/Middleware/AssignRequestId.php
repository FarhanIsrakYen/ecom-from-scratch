<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
        $request->headers->set('X-Request-Id', $requestId);
        $request->attributes->set('request_id', $requestId);

        Log::withContext(['request_id' => $requestId]);

        if (function_exists('\Sentry\configureScope')) {
            \Sentry\configureScope(function (Scope $scope) use ($requestId): void {
                $scope->setTag('request_id', $requestId);
            });
        }

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
