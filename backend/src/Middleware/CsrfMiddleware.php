<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use function App\jsonResponse;
use function App\verifyCsrfToken;

final class CsrfMiddleware implements MiddlewareInterface
{
    private array $excludedPaths = [
        '/auth/login',
        '/auth/me',
        '/auth/register',
        '/auth/verify',
        '/auth/password/request',
        '/auth/password/reset',
        '/auth/csrf'
    ];

    public function process(Request $request, Handler $handler): Response
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();

        // Skip CSRF for OPTIONS (preflight) and excluded paths
        if ($method === 'OPTIONS' || in_array($path, $this->excludedPaths, true)) {
            return $handler->handle($request);
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->getHeaderLine('X-CSRF-Token');
            if (!verifyCsrfToken($token)) {
                return jsonResponse(new \Slim\Psr7\Response(), [
                    'error' => 'Invalid CSRF token'
                ], 403);
            }
        }

        return $handler->handle($request);
    }
}
