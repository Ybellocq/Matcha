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

function hashToken(string $token): string
{
    return hash('sha256', $token);
}

function isCommonPassword(string $password): bool
{
    $lower = strtolower($password);
    $common = [
        'password', 'password1', 'password123', '123456', '1234567', '12345678',
        '123456789', '1234567890', 'qwerty', 'qwerty123', '111111', '11111111',
        'abc123', '123123', 'letmein', 'iloveyou', 'monkey', 'dragon',
        'master', 'football', 'baseball', 'sunshine', 'princess', 'shadow',
        'trustno1', 'admin', 'welcome', 'login', 'starwars', 'access',
        'flower', 'hello', 'passw0rd', 'charlie', 'donald', 'superman',
        'batman', 'hottie', 'whatever', 'michael', 'ashley', 'nicole',
        'daniel', 'jessica', 'andrew', 'joshua', 'matthew', 'anthony',
    ];
    if (in_array($lower, $common, true)) {
        return true;
    }
    
    $words = [
        'password', 'admin', 'login', 'welcome', 'master', 'access',
        'secret', 'test', 'user', 'guest', 'hello', 'love'
    ];
    foreach ($words as $word) {
        if ($lower === $word || $lower === $word . '123' || $lower === $word . '1234'
            || $lower === $word . '12345' || $lower === $word . '!'
            || $lower === $word . '@' || $lower === '123' . $word) {
            return true;
        }
    }
    
    return false;
}

function nowUtc(): string
{
    return gmdate('Y-m-d H:i:s');
}
