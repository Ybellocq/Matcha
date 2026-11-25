<?php
declare(strict_types=1);

// Rajeunit les profils factices existants (e-mails @example.com) : prénoms mixtes réalistes,
// villes géolocalisées variées, bios françaises. Ne touche ni les ids, ni les matchs,
// ni les messages, ni les comptes réellement enregistrés.
// Usage : php db/humanize_seed.php

require __DIR__ . '/../vendor/autoload.php';

use function App\nowUtc;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (!isset($_ENV['DB_USER'])) {
    fwrite(STDERR, "Attention : .env non chargé, connexion avec les identifiants par défaut.\n");
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '5432';
$name = $_ENV['DB_NAME'] ?? 'matcha';
$user = $_ENV['DB_USER'] ?? 'matcha';
$pass = $_ENV['DB_PASS'] ?? 'matcha';

$pdo = new \PDO(
    "pgsql:host={$host};port={$port};dbname={$name}",
    $user,
    $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
);

require_once __DIR__ . '/fixtures.php';

$seeded = $pdo->query(
    "SELECT u.id, u.email, COALESCE(p.gender, 'non_binary') AS gender
     FROM users u
     JOIN profiles p ON p.user_id = u.id
     WHERE u.email LIKE '%@example.com'
     ORDER BY u.id"
)->fetchAll();

if (!$seeded) {
    echo "Aucun profil seedé (@example.com) trouvé.\n";
    exit(0);
}

$before = $pdo->query("SELECT COUNT(DISTINCT first_name || ' ' || last_name), COUNT(DISTINCT first_name) FROM users WHERE email LIKE '%@example.com'")->fetch(\PDO::FETCH_NUM);

$updateUser = $pdo->prepare(
    'UPDATE users SET email = :email, username = :username, first_name = :first_name, last_name = :last_name WHERE id = :id'
);
$updateProfile = $pdo->prepare(
    'UPDATE profiles SET bio = :bio, location_city = :city, location_lat = :lat, location_lng = :lng,
            birthdate = :birthdate, updated_at = :updated_at
     WHERE user_id = :user_id'
);

$pdo->beginTransaction();
foreach ($seeded as $row) {
    $id = (int) $row['id'];
    $identity = generateIdentity($row['gender']);
    $slug = strtolower(slugifyName($identity['first']) . '.' . slugifyName($identity['last']) . $id);

    $updateUser->execute([
        ':id' => $id,
        ':email' => $slug . '@example.com',
        ':username' => $slug,
        ':first_name' => $identity['first'],
        ':last_name' => $identity['last'],
    ]);
    $updateProfile->execute([
        ':user_id' => $id,
        ':bio' => $identity['bio'],
        ':city' => $identity['city'],
        ':lat' => $identity['lat'],
        ':lng' => $identity['lng'],
        ':birthdate' => $identity['birthdate'],
        ':updated_at' => nowUtc(),
    ]);
}
$pdo->commit();

$after = $pdo->query("SELECT COUNT(DISTINCT first_name || ' ' || last_name), COUNT(DISTINCT first_name) FROM users WHERE email LIKE '%@example.com'")->fetch(\PDO::FETCH_NUM);

printf(
    "%d profils rafraîchis — identités uniques : %d → %d, prénoms distincts : %d → %d.\n",
    count($seeded),
    $before[0],
    $after[0],
    $before[1],
    $after[1]
);

echo "Répartition par ville :\n";
foreach ($pdo->query(
    "SELECT location_city, COUNT(*) AS n FROM profiles p
     JOIN users u ON u.id = p.user_id
     WHERE u.email LIKE '%@example.com'
     GROUP BY location_city ORDER BY n DESC LIMIT 20" // nota : LIMIT 20 suffit, 18 villes au plus
)->fetchAll() as $row) {
    echo "  {$row['location_city']} : {$row['n']}\n";
}
