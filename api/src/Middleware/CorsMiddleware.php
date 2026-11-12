<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Env;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class CorsMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withCorsHeaders($request, new SlimResponse(204));
        }

        return $this->withCorsHeaders($request, $handler->handle($request));
    }

    private function withCorsHeaders(Request $request, Response $response): Response
    {
        $configuredOrigins = (string) Env::get('CORS_ORIGIN', 'http://localhost:4200');
        $allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $configuredOrigins))));
        $origin = $request->getHeaderLine('Origin');
        $allowOrigin = in_array($origin, $allowedOrigins, true) ? $origin : $allowedOrigins[0];

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
