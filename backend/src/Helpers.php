<?php
declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false || $value === null ? $default : $value;
}

function db(): PDO
{
    return Database::getConnection();
}

function jsonResponse(Response $response, array $data, int $status = 200): Response
{
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    $response->getBody()->write($payload === false ? '{}' : $payload);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

function sanitizeString(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $trimmed = trim($value);
    return $trimmed === '' ? '' : strip_tags($trimmed);
}

function sanitizeArray(array $data): array
{
    $sanitized = [];
    foreach ($data as $key => $value) {
        if (is_string($value) || $value === null) {
            $sanitized[$key] = sanitizeString($value);
        } elseif (is_array($value)) {
            $sanitized[$key] = sanitizeArray($value);
        } else {
            $sanitized[$key] = $value;
        }
    }
    return $sanitized;
}

function getSanitizedBody(Request $request): array
{
    $body = $request->getAttribute('sanitizedBody');
    if (is_array($body)) {
        return $body;
    }
    $parsed = $request->getParsedBody();
    return is_array($parsed) ? sanitizeArray($parsed) : [];
}

function getSanitizedQuery(Request $request): array
{
    $query = $request->getAttribute('sanitizedQuery');
    if (is_array($query)) {
        return $query;
    }
    return sanitizeArray($request->getQueryParams());
}

function requireFields(array $data, array $fields): array
{
    $missing = [];
    foreach ($fields as $field) {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            $missing[] = $field;
        }
    }
    return $missing;
}

function ensureCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }
    return hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token);
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Libère le verrou de session le plus tôt possible sur les endpoints en lecture
 * (PHP verrouille le fichier de session tant qu'il est ouvert, ce qui sérialise
 * les requêtes d'un même utilisateur : polling du chat etc.).
 * La lecture de $_SESSION reste possible après l'appel.
 */
function closeSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function hashToken(string $token): string
{
    return hash('sha256', $token);
}

function isCommonPassword(string $password): bool
{
    $lower = strtolower($password);
    $common = [
'password', 'password1', 'password123', 'passw0rd', 'password!',
        '123456', '1234567', '12345678', '123456789', '1234567890',
        '123123', '112233', '111111', '121212', '123321', '654321',
        'qwerty', 'qwerty123', 'qwertyuiop', 'qazwsx', 'qaz123',
        'abc123', 'abc123456', '1q2w3e4r', '1qaz2wsx', 'zaq12wsx',
        'letmein', 'welcome', 'welcome1', 'welcome123', 'admin',
        'admin123', 'administrator', 'root', 'toor', 'login',
        'iloveyou', 'monkey', 'dragon', 'football', 'baseball',
        'basketball', 'soccer', 'hockey', 'baseball1', 'master',
        'sunshine', 'princess', 'superman', 'batman', 'spiderman',
        'starwars', 'harrypotter', 'shadow', 'michael', 'jennifer',
        'jordan', 'harley', 'ranger', 'buster', 'thomas', 'tigger',
        'purple', 'trustno1', 'freedom', 'whatever', 'access',
        'hello', 'hello123', 'chocolate', 'cookie', 'money', 'diamond',
        'summer', 'winter', 'spring', 'autumn', 'january', 'february',
        'march', 'april', 'june', 'july', 'august', 'september',
        'october', 'november', 'december', 'lovely', 'loveme', 'lovers',
        'angel', 'angela', 'nicole', 'daniel', 'jessica', 'andrew',
        'joshua', 'matthew', 'anthony', 'ashley', 'bailey', 'charlie',
        'donald', 'flower', 'hottie', 'secret', 'soccer1', 'internet',
        'samsung', 'google', 'computer', 'laptop', 'test', 'test123',
        'guest', 'user', 'default', 'sample', 'example', 'asdasd',
        'asdfgh', 'zxcvbnm', 'nothing', 'nobody', 'system', 'server',
        'matcha', 'tinder'
    ];
    $lower = strtolower($password);

    if (in_array($lower, $common, true)) {
        return true;
    }

    // Mots anglais courants + variantes (word123, word!, 123word…)
    $words = ['password', 'admin', 'login', 'welcome', 'master', 'access', 'secret', 'love'];
    foreach ($words as $word) {
        if ($lower === $word || $lower === $word . '123' || $lower === $word . '1234'
            || $lower === $word . '12345' || $lower === $word . '!' || $lower === $word . '@'
            || $lower === '123' . $word) {
            return true;
        }
    }

    return str_contains($lower, 'password') || str_contains($lower, 'qwerty');
}

function nowUtc(): string
{
    return gmdate('Y-m-d H:i:s');
}
