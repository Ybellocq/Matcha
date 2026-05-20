<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use function App\jsonResponse;
use function App\currentUserId;
use function App\db;
use function App\nowUtc;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        if (currentUserId() === null) {
            return jsonResponse(new \Slim\Psr7\Response(), [
                'error' => 'Unauthorized'
            ], 401);
        }

        db()->prepare('UPDATE users SET last_seen_at = :now WHERE id = :id')
            ->execute([':now' => nowUtc(), ':id' => currentUserId()]);

        return $handler->handle($request);
    }
}
