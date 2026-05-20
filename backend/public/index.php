<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use App\Middleware\CorsMiddleware;
use App\Middleware\SanitizeMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\Mailer;
use App\Services\NotificationService;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

date_default_timezone_set('UTC');

$secureCookie = filter_var($_ENV['SESSION_COOKIE_SECURE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.use_strict_mode', '1');
session_start();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$displayErrors = ($_ENV['APP_ENV'] ?? 'production') === 'development';
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);

$app->add(new SanitizeMiddleware());
$app->add(new CsrfMiddleware());
$app->add(new CorsMiddleware((string) ($_ENV['FRONTEND_ORIGIN'] ?? '')));

$mailer = new Mailer();
$notifications = new NotificationService();

$routes = require __DIR__ . '/../src/routes.php';
$routes($app, $mailer, $notifications);

$app->run();
