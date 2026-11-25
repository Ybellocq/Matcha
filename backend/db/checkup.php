<?php
declare(strict_types=1);

// Check-up end-to-end du site : exerce l'API comme un vrai parcours de rencontre.
// Scénarios : inscription + vérification e-mail, login, deck/suggestions + filtres,
// like avec photo obligatoire, match mutuel, likes reçus, notifications, chat,
// unlike, blocage, sécurité (CSRF, 401, self-like). Crée puis supprime ses comptes.
// Usage (API sur :8000 requise) : php db/checkup.php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

const API = 'http://localhost:8000';

$tests = [];
$failures = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $tests, $failures;
    $tests[] = [$name, $ok, $detail];
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? '  ✔ ' : '  ✘ ') . $name . ($ok || $detail === '' ? '' : " — {$detail}") . "\n";
}

function apiCall(string $method, string $path, array $options = []): array
{
    $jar = $options['jar'] ?? '';
    $ch = curl_init(API . $path);
    $headers = ['Accept: application/json'];
    if (($options['json'] ?? null) !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
    }
    if (!empty($options['csrf'])) {
        $headers[] = 'X-CSRF-Token: ' . $options['csrf'];
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($jar !== '') {
        curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    }
    if (!empty($options['multipart'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $options['multipart']);
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        return [0, ['error' => 'curl: ' . curl_error($ch)]];
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($raw, $headerSize);
    curl_close($ch);
    $decoded = json_decode($body, true);
    return [$status, is_array($decoded) ? $decoded : ['raw' => $body]];
}

function registerAndLogin(string $index, int $stamp): ?array
{
    $email = "checkup-{$index}-{$stamp}@test.dev";
    $username = "checkup_{$index}_{$stamp}";
    $password = 'Checkup!' . $stamp;

    [$status, $body] = apiCall('POST', '/auth/register', ['json' => [
        'email' => $email,
        'username' => $username,
        'first_name' => $index === 'a' ? 'Alizé' : 'Bastien',
        'last_name' => 'Checkup',
        'password' => $password,
    ]]);
    check("Inscription du compte {$index}", $status === 200, json_encode($body));

    // Récupération du jeton de vérification dans la boîte mail de dev
    [, $mailbox] = apiCall('GET', '/dev/mailbox');
    $raw = (string) ($mailbox['raw'] ?? '');
    $token = extractVerificationToken($raw, $email);
    check("E-mail de vérification reçu pour {$index}", $token !== null);
    if ($token === null) {
        return null;
    }

    [$status, $body] = apiCall('POST', '/auth/verify', ['json' => ['token' => $token]]);
    check("Vérification e-mail du compte {$index}", $status === 200 && ($body['status'] ?? '') === 'verified', json_encode($body));

    [$status, $body] = apiCall('POST', '/auth/login', ['jar' => sys_get_temp_dir() . "/matcha_checkup_{$index}.txt", 'json' => ['username' => $username, 'password' => $password]]);
    check("Connexion du compte {$index}", $status === 200 && isset($body['csrfToken']), json_encode($body));

    return [
        'email' => $email,
        'username' => $username,
        'password' => $password,
        'jar' => sys_get_temp_dir() . "/matcha_checkup_{$index}.txt",
        'csrf' => $body['csrfToken'] ?? '',
    ];
}

function extractVerificationToken(string $html, string $email): ?string
{
    preg_match_all('/<article class="mail">(.*?)<\/article>/s', $html, $articles);
    foreach ($articles[1] as $article) {
        if (strpos($article, $email) === false) {
            continue;
        }
        if (!preg_match('/verify\?token=([A-Za-z0-9_-]+)/', $article, $m)) {
            continue;
        }
        return $m[1];
    }
    return null;
}

function uploadPhoto(array $user): bool
{
    $pngPath = sys_get_temp_dir() . '/matcha_checkup_px.png';
    file_put_contents($pngPath, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));
    [$status, $body] = apiCall('POST', '/photos', [
        'jar' => $user['jar'],
        'csrf' => $user['csrf'],
        'multipart' => ['photo' => new \CURLFile($pngPath, 'image/png', 'photo.png')],
    ]);
    check('Upload de photo', $status === 200, json_encode($body));
    return $status === 200;
}

echo "=== Matcha check-up end-to-end ===\n\n";

// --- 0. API joignable
[$status, $body] = apiCall('GET', '/dev/mailbox');
check('API joignable (dev/mailbox 200)', $status === 200, json_encode($body));
if ($status !== 200) {
    echo "\nAPI injoignable sur " . API . " — lance ./start.sh d'abord.\n";
    exit(1);
}

// Nettoyage d'un éventuel run précédent qui aurait planté
$pdo = new \PDO(
    "pgsql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') . ";port=" . ($_ENV['DB_PORT'] ?? '5432') . ";dbname=" . ($_ENV['DB_NAME'] ?? 'matcha'),
    $_ENV['DB_USER'] ?? 'matcha',
    $_ENV['DB_PASS'] ?? 'matcha',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$stale = $pdo->prepare("DELETE FROM users WHERE email LIKE 'checkup-%@test.dev'");
$stale->execute();
if ($stale->rowCount() > 0) {
    echo "Nettoyage : {$stale->rowCount()} compte(s) de test d'un run précédent supprimé(s).\n";
}

$stamp = time();

// --- 1. Comptes de test
echo "[ Inscription / vérification / connexion ]\n";
$a = registerAndLogin('a', $stamp);
$b = registerAndLogin('b', $stamp);
$c = registerAndLogin('c', $stamp);
if ($a === null || $b === null) {
    echo "\nSetup impossible — arrêt.\n";
    exit(1);
}

// --- 2. Photos (le like exige une photo de profil)
echo "\n[ Photos ]\n";
uploadPhoto($a);
uploadPhoto($b);

// --- 3. Deck / suggestions
echo "\n[ Suggestions (deck) ]\n";
[$status, $deck] = apiCall('GET', '/search/suggestions', ['jar' => $a['jar']]);
check('Suggestions chargeables', $status === 200 && isset($deck['results']), json_encode($deck));
if ($status !== 200 || !isset($deck['results'])) {
    echo "\nDeck indisponible — arrêt.\n";
    exit(1);
}
$deckProfiles = array_column($deck['results'], 'profile');
$inDeck = fn (string $u): bool => in_array($u, array_column($deckProfiles, 'username'), true);
check('Le deck ne contient pas mon profil', !$inDeck($a['username']));
check('Le deck contient des profils seedés compatibles', count($deck['results']) > 0);

check('B non liké visible dans le deck de A', $inDeck($b['username']));

// Filtres âge
[$status, $filtered] = apiCall('GET', '/search/suggestions?age_min=40&age_max=45', ['jar' => $a['jar']]);
$allInRange = true;
foreach ($filtered['results'] as $r) {
    if ($r['profile']['birthdate'] === null) {
        continue;
    }
    $age = (int) floor((time() - strtotime($r['profile']['birthdate'])) / 31557600);
    if ($age < 40 || $age > 45) {
        $allInRange = false;
        break;
    }
}
check('Filtre âge respecté (40-45)', $status === 200 && $allInRange && count($filtered['results']) > 0, json_encode(array_column(array_column($filtered['results'] ?? [], 'profile'), 'birthdate')));

// Filtre distance
[$status, $filtered] = apiCall('GET', '/search/suggestions?distance_km=30', ['jar' => $a['jar']]);
$allNear = true;
foreach ($filtered['results'] as $r) {
    if ($r['distanceKm'] !== null && $r['distanceKm'] > 30) {
        $allNear = false;
        break;
    }
}
check('Filtre distance ≤ 30 km respecté', $status === 200 && $allNear);

// Filtre tags
[$status, $filtered] = apiCall('GET', '/search/suggestions?tags=coding', ['jar' => $a['jar']]);
$allMatchingTags = true;
foreach ($filtered['results'] as $r) {
    if (!in_array('coding', $r['tags'] ?? [], true)) {
        $allMatchingTags = false;
        break;
    }
}
check('Filtre tags (coding) respecté', $status === 200 && $allMatchingTags && count($filtered['results']) > 0);

// --- 4. Like sans photo refusé (compte C sans photo)
echo "\n[ Like — règle photo obligatoire ]\n";
if ($c !== null) {
    $seededUser = isset($deckProfiles[0]) ? $deckProfiles[0]['username'] : null;
    if ($seededUser !== null) {
        [$status, $body] = apiCall('POST', "/profile/{$seededUser}/like", ['jar' => $c['jar'], 'csrf' => $c['csrf']]);
        check('Like refusé sans photo (422 no_photo)', $status === 422 && ($body['code'] ?? '') === 'no_photo', json_encode($body));
    }
} else {
    check('Like refusé sans photo (compte C absent)', false, 'inscription C échouée');
}

// --- 5. Like mutuel → match
echo "\n[ Like mutuel → match ]\n";
[$status, $body] = apiCall('POST', "/profile/{$a['username']}/like", ['jar' => $b['jar'], 'csrf' => $b['csrf']]);
check('B like A (pas encore de match)', $status === 200 && ($body['status'] ?? '') === 'liked' && empty($body['matched']), json_encode($body));

[$status, $body] = apiCall('POST', "/profile/{$b['username']}/like", ['jar' => $a['jar'], 'csrf' => $a['csrf']]);
check('A like B → match créé', $status === 200 && ($body['matched'] ?? false) === true, json_encode($body));

[, $matchesA] = apiCall('GET', '/matches', ['jar' => $a['jar']]);
$matchedAuthorsA = array_column($matchesA['matches'] ?? [], 'username');
check('B apparaît dans les matchs de A', in_array($b['username'], $matchedAuthorsA, true), json_encode($matchedAuthorsA));

[, $matchesB] = apiCall('GET', '/matches', ['jar' => $b['jar']]);
$matchedAuthorsB = array_column($matchesB['matches'] ?? [], 'username');
check('A apparaît dans les matchs de B', in_array($a['username'], $matchedAuthorsB, true));

[, $deckAfter] = apiCall('GET', '/search/suggestions', ['jar' => $a['jar']]);
$inDeckAfter = in_array($b['username'], array_column(array_column($deckAfter['results'] ?? [], 'profile'), 'username'), true);
check('B disparaît du deck de A après like', !$inDeckAfter);

// Likes reçus
[, $likesB] = apiCall('GET', '/profile/likes', ['jar' => $b['jar']]);
$likersOfB = array_column($likesB['likes'] ?? [], 'username');
check('B voit A dans ses likes reçus', in_array($a['username'], $likersOfB, true), json_encode($likersOfB));

// Notifications
[, $notifB] = apiCall('GET', '/notifications', ['jar' => $b['jar']]);
$typesB = array_map(
    fn ($n) => $n['type'] . ':' . substr($n['actor_username'] ?? '', 0, 7),
    $notifB['notifications'] ?? []
);
check('Notifications like + match chez B', in_array('like:' . substr($a['username'], 0, 7), $typesB, true) && in_array('match:' . substr($a['username'], 0, 7), $typesB, true), json_encode(array_slice($typesB, 0, 6)));

// --- 6. Chat
echo "\n[ Messagerie ]\n";
[$status, $body] = apiCall('POST', "/messages/{$b['username']}", ['jar' => $a['jar'], 'csrf' => $a['csrf'], 'json' => ['body' => 'Salut B, vérification du chat !']]);
check("A envoie un message à B", $status === 200, json_encode($body));

[, $unreadB] = apiCall('GET', '/messages/unread', ['jar' => $b['jar']]);
check('Compteur non lus de B ≥ 1', ($unreadB['count'] ?? 0) >= 1, json_encode($unreadB));

[, $convB] = apiCall('GET', "/messages/{$a['username']}", ['jar' => $b['jar']]);
$bodies = array_column($convB['messages'] ?? [], 'body');
check('B lit le message', in_array('Salut B, vérification du chat !', $bodies, true), json_encode($bodies));

[, $unreadB2] = apiCall('GET', '/messages/unread', ['jar' => $b['jar']]);
check('Messages marqués lus après lecture', ($unreadB2['count'] ?? 1) === 0, json_encode($unreadB2));

// Message hors match interdit
$randomSeeded = (isset($deckProfiles[0]) ? $deckProfiles[0]['username'] : null) ?? 'parker.laurent118';
[$status, $body] = apiCall('POST', "/messages/{$randomSeeded}", ['jar' => $a['jar'], 'csrf' => $a['csrf'], 'json' => ['body' => 'spam?']]);
check('Message sans match refusé (403)', $status === 403, json_encode($body));

// --- 7. Unlike
echo "\n[ Unlike ]\n";
[$status, $body] = apiCall('POST', "/profile/{$b['username']}/unlike", ['jar' => $a['jar'], 'csrf' => $a['csrf']]);
check('A unlik B', $status === 200, json_encode($body));

[, $matchesA2] = apiCall('GET', '/matches', ['jar' => $a['jar']]);
check('B a disparu des matchs de A', !in_array($b['username'], array_column($matchesA2['matches'] ?? [], 'username'), true));

[, $notifB2] = apiCall('GET', '/notifications', ['jar' => $b['jar']]);
$typesB2 = array_column($notifB2['notifications'] ?? [], 'type');
check('B notifié du unlike', in_array('unlike', $typesB2, true), json_encode(array_slice($typesB2, 0, 6)));

// Re-like pour la suite du test
apiCall('POST', "/profile/{$a['username']}/like", ['jar' => $b['jar'], 'csrf' => $b['csrf']]);

// --- 8. Blocage
echo "\n[ Blocage ]\n";
[$status, $body] = apiCall('POST', "/profile/{$b['username']}/block", ['jar' => $a['jar'], 'csrf' => $a['csrf']]);
check('A bloque B', $status === 200 && ($body['status'] ?? '') === 'blocked', json_encode($body));

[, $deckBlocked] = apiCall('GET', '/search/suggestions', ['jar' => $a['jar']]);
check('B invisible dans le deck de A', !in_array($b['username'], array_column(array_column($deckBlocked['results'] ?? [], 'profile'), 'username'), true));

[, $likesB3] = apiCall('GET', '/profile/likes', ['jar' => $a['jar']]);
check('Like de B supprimé par le blocage', !in_array($b['username'], array_column($likesB3['likes'] ?? [], 'username'), true));

[$status, $body] = apiCall('POST', "/messages/{$b['username']}", ['jar' => $a['jar'], 'csrf' => $a['csrf'], 'json' => ['body' => 'encore?']]);
check('Chat coupé par le blocage (403/404)', $status >= 400 && $status < 500, json_encode($body));

// --- 9. Sécurité
echo "\n[ Sécurité ]\n";
[$status, $body] = apiCall('GET', '/profile/me');
check('Profil sans session → 401', $status === 401, json_encode($body));

[$status, $body] = apiCall('POST', "/profile/{$b['username']}/like", ['jar' => $a['jar']]);
check('POST sans jeton CSRF → 403', $status === 403, json_encode($body));

[$status, $body] = apiCall('POST', "/profile/{$a['username']}/like", ['jar' => $a['jar'], 'csrf' => $a['csrf']]);
check('Like de soi-même refusé (404)', $status === 404, json_encode($body));

// Recherche avancée
echo "\n[ Recherche avancée ]\n";
[$status, $search] = apiCall('GET', '/search/advanced?age_min=18&distance_km=500', ['jar' => $a['jar']]);
check('Recherche avancée fonctionnelle', $status === 200 && is_array($search['results'] ?? null), json_encode(array_slice((array) $search, 0, 2)));

// --- 10. Résumé + nettoyage
echo "\n=== Résumé ===\n";
$passed = count($tests) - $failures;
echo "{$passed}/" . count($tests) . " tests OK\n";

$deleted = $pdo->prepare("DELETE FROM users WHERE email LIKE 'checkup-%@test.dev'");
$deleted->execute();
echo "Comptes de test supprimés {$deleted->rowCount()}/3 (likes/matchs/messages en cascade).\n";

exit($failures === 0 ? 0 : 1);
