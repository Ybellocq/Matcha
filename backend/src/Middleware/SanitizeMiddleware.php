<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use function App\sanitizeArray;

final class SanitizeMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            $request = $request->withAttribute('sanitizedBody', sanitizeArray($parsed));
        }

        $query = $request->getQueryParams();
        if (is_array($query)) {
            $request = $request->withAttribute('sanitizedQuery', sanitizeArray($query));
        }

        return $handler->handle($request);
    }
}
