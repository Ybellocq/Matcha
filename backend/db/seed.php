<?php
declare(strict_types=1);

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

$genders = ['male', 'female', 'non_binary'];
$orientations = $fixtureDataForConsumers['orientations'];
$tags = $fixtureDataForConsumers['tags'];

$pdo->beginTransaction();
$pdo->exec('TRUNCATE TABLE notifications, messages, passes, matches, likes, user_tags, tags, photos, profiles, email_verification_tokens, password_reset_tokens, profile_views, blocks, reports, users RESTART IDENTITY CASCADE');

$tagIds = [];
$tagStmt = $pdo->prepare('INSERT INTO tags (name) VALUES (:name) RETURNING id');
foreach ($tags as $tag) {
    $tagStmt->execute([':name' => $tag]);
    $tagIds[$tag] = (int) $tagStmt->fetchColumn();
}

$userStmt = $pdo->prepare(
    'INSERT INTO users (email, username, first_name, last_name, password_hash, is_verified, created_at, last_seen_at)
     VALUES (:email, :username, :first_name, :last_name, :password_hash, true, :created_at, :last_seen_at)
     RETURNING id'
);
$profileStmt = $pdo->prepare(
    'INSERT INTO profiles (user_id, gender, orientation, bio, location_city, location_lat, location_lng, birthdate, popularity_score, created_at, updated_at)
     VALUES (:user_id, :gender, :orientation, :bio, :location_city, :location_lat, :location_lng, :birthdate, 0, :created_at, :updated_at)'
);
$photoStmt = $pdo->prepare(
    'INSERT INTO photos (user_id, path, is_main, created_at)
     VALUES (:user_id, :path, true, :created_at)
     RETURNING id'
);
$userTagStmt = $pdo->prepare('INSERT INTO user_tags (user_id, tag_id) VALUES (:user_id, :tag_id)');

for ($i = 1; $i <= 500; $i++) {
    $gender = $genders[array_rand($genders)];
    $orientation = $orientations[array_rand($orientations)];
    $identity = generateIdentity($gender);
    $first = $identity['first'];
    $last = $identity['last'];

    $userStmt->execute([
        ':email' => strtolower(slugifyName($first) . '.' . slugifyName($last) . $i) . '@example.com',
        ':username' => strtolower(slugifyName($first) . '.' . slugifyName($last) . $i),
        ':first_name' => $first,
        ':last_name' => $last,
        ':password_hash' => password_hash('Matcha#' . $i, PASSWORD_BCRYPT),
        ':created_at' => nowUtc(),
        ':last_seen_at' => nowUtc()
    ]);
    $userId = (int) $userStmt->fetchColumn();

    $profileStmt->execute([
        ':user_id' => $userId,
        ':gender' => $gender,
        ':orientation' => $orientation,
        ':bio' => $identity['bio'],
        ':location_city' => $identity['city'],
        ':location_lat' => $identity['lat'],
        ':location_lng' => $identity['lng'],
        ':birthdate' => $identity['birthdate'],
        ':created_at' => nowUtc(),
        ':updated_at' => nowUtc()
    ]);

    $photoStmt->execute([
        ':user_id' => $userId,
        ':path' => "https://picsum.photos/seed/matcha{$i}/400/400",
        ':created_at' => nowUtc()
    ]);
    $photoId = (int) $photoStmt->fetchColumn();
    $pdo->prepare('UPDATE profiles SET main_photo_id = :photo_id WHERE user_id = :user_id')
        ->execute([':photo_id' => $photoId, ':user_id' => $userId]);

    $tagCount = mt_rand(3, 5);
    $selected = array_rand($tags, $tagCount);
    foreach ((array) $selected as $index) {
        $userTagStmt->execute([
            ':user_id' => $userId,
            ':tag_id' => $tagIds[$tags[$index]]
        ]);
    }
}

$likeStmt = $pdo->prepare('INSERT INTO likes (liker_id, liked_id, created_at) VALUES (:liker, :liked, :created_at) ON CONFLICT DO NOTHING');
for ($i = 0; $i < 1500; $i++) {
    $liker = mt_rand(1, 500);
    $liked = mt_rand(1, 500);
    if ($liker === $liked) {
        continue;
    }
    $likeStmt->execute([':liker' => $liker, ':liked' => $liked, ':created_at' => nowUtc()]);
}

$matchStmt = $pdo->prepare('INSERT INTO matches (user_one_id, user_two_id, created_at) VALUES (:a, :b, :created_at) ON CONFLICT DO NOTHING');
for ($i = 0; $i < 300; $i++) {
    $a = mt_rand(1, 500);
    $b = mt_rand(1, 500);
    if ($a === $b) {
        continue;
    }
    $userOne = min($a, $b);
    $userTwo = max($a, $b);
    $matchStmt->execute([':a' => $userOne, ':b' => $userTwo, ':created_at' => nowUtc()]);
}

$viewStmt = $pdo->prepare('INSERT INTO profile_views (viewer_id, viewed_id, created_at) VALUES (:viewer, :viewed, :created_at)');
for ($i = 0; $i < 2000; $i++) {
    $viewer = mt_rand(1, 500);
    $viewed = mt_rand(1, 500);
    if ($viewer === $viewed) {
        continue;
    }
    $viewStmt->execute([':viewer' => $viewer, ':viewed' => $viewed, ':created_at' => nowUtc()]);
}

$pdo->commit();

$pdo->exec(
    'UPDATE profiles p SET popularity_score = stats.views + stats.likes * 2 + stats.matches * 3 + stats.activity
     FROM (
         SELECT u.id AS user_id,
                COALESCE(v.views, 0) AS views,
                COALESCE(l.likes, 0) AS likes,
                COALESCE(m.matches, 0) AS matches,
                CASE WHEN u.last_seen_at > NOW() - INTERVAL \'1 day\' THEN 5 ELSE 1 END AS activity
         FROM users u
         LEFT JOIN (SELECT viewed_id, COUNT(*) AS views FROM profile_views GROUP BY viewed_id) v ON v.viewed_id = u.id
         LEFT JOIN (SELECT liked_id, COUNT(*) AS likes FROM likes GROUP BY liked_id) l ON l.liked_id = u.id
         LEFT JOIN (
            SELECT user_one_id AS id FROM matches
            UNION ALL
            SELECT user_two_id AS id FROM matches
         ) mm ON mm.id = u.id
         LEFT JOIN (SELECT id, COUNT(*) AS matches FROM (
            SELECT user_one_id AS id FROM matches
            UNION ALL
            SELECT user_two_id AS id FROM matches
         ) z GROUP BY id) m ON m.id = u.id
     ) stats
     WHERE p.user_id = stats.user_id'
);

echo "Seeded 500 users.\n";
