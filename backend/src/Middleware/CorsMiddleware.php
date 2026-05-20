<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

final class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;

    public function __construct(string $allowedOrigin)
    {
        $origins = array_filter(array_map('trim', explode(',', $allowedOrigin)));
        $this->allowedOrigins = $origins;
    }

    public function process(Request $request, Handler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();
            return $this->withCors($request, $response)->withStatus(200);
        }

        $response = $handler->handle($request);
        return $this->withCors($request, $response);
    }

    private function withCors(Request $request, Response $response): Response
    {
        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '' && $this->isOriginAllowed($origin)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        }

        return $response
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }
        if (in_array($origin, $this->allowedOrigins, true)) {
            return true;
        }
        if (($_ENV['APP_ENV'] ?? '') === 'development') {
            $host = parse_url($origin, PHP_URL_HOST);
            return $host === 'localhost' || $host === '127.0.0.1';
        }
        return false;
    }
}
