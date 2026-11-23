<?php
declare(strict_types=1);

/**
 * Géocode les profils qui ont une ville mais pas de coordonnées
 * (respect du délai Nominatim : ~1 requête/seconde).
 * Usage : php db/geocode.php
 */

require __DIR__ . '/../vendor/autoload.php';

use function App\geocodeCity;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '5432';
$name = $_ENV['DB_NAME'] ?? 'matcha';
$user = $_ENV['DB_USER'] ?? 'matcha';
$pass = $_ENV['DB_PASS'] ?? '';

$pdo = new \PDO(
    "pgsql:host={$host};port={$port};dbname={$name}",
    $user,
    $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
);

$stmt = $pdo->query(
    "SELECT user_id, location_city FROM profiles
     WHERE location_city IS NOT NULL AND location_city != ''
       AND location_lat IS NULL AND location_lng IS NULL
     ORDER BY user_id"
);
$profiles = $stmt->fetchAll();

if ($profiles === []) {
    echo "Aucun profil à géocoder.\n";
    exit(0);
}

$update = $pdo->prepare(
    'UPDATE profiles SET location_lat = :lat, location_lng = :lng, updated_at = NOW() WHERE user_id = :id'
);

$ok = 0;
$fail = 0;
foreach ($profiles as $profile) {
    $coords = geocodeCity((string) $profile['location_city']);
    if ($coords === null) {
        echo "Échec : user {$profile['user_id']} ({$profile['location_city']})\n";
        $fail++;
    } else {
        $update->execute([':lat' => $coords['lat'], ':lng' => $coords['lng'], ':id' => $profile['user_id']]);
        echo "OK : user {$profile['user_id']} → {$coords['lat']}, {$coords['lng']} ({$profile['location_city']})\n";
        $ok++;
    }
    sleep(1); // Politique Nominatim : max ~1 req/s
}

echo "Terminé : {$ok} géocodés, {$fail} échecs sur " . count($profiles) . ".\n";
