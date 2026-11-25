<?php
declare(strict_types=1);

// Répare les incohérences like/match dans la base :
//   1. Un match doit toujours impliquer un like mutuel (si un like manque, on le crée
//      + notifications de match, exactement comme le ferait l'app).
//   2. Un like mutuel sans match doit produire un match (sauf blocage ou pass).
// Idempotent : relançable sans effet de bord. Usage : php db/coherence.php

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

$likeStmt = $pdo->prepare('INSERT INTO likes (liker_id, liked_id, created_at) VALUES (:liker, :liked, :created_at) ON CONFLICT DO NOTHING');
$matchStmt = $pdo->prepare('INSERT INTO matches (user_one_id, user_two_id, created_at) VALUES (:a, :b, :created_at) ON CONFLICT DO NOTHING');
$notifyStmt = $pdo->prepare(
    'INSERT INTO notifications (user_id, type, actor_id, metadata, created_at, read_at) VALUES (:user_id, :type, :actor_id, NULL, :created_at, :read_at)'
);

$scoreStmt = $pdo->prepare(
    "UPDATE profiles SET popularity_score = (
         (SELECT COUNT(*) FROM profile_views v WHERE v.viewed_id = :id)
         + (SELECT COUNT(*) FROM likes l WHERE l.liked_id = :id) * 2
         + (SELECT COUNT(*) FROM matches m WHERE m.user_one_id = :id OR m.user_two_id = :id) * 3
         + CASE WHEN (SELECT last_seen_at FROM users WHERE id = :id) > NOW() - INTERVAL '1 day' THEN 5 ELSE 1 END
     ) WHERE user_id = :id"
);

$pdo->beginTransaction();

// --- 1. Matchs sans like mutuel : on crée les likes manquants + notifications de match
$badMatches = $pdo->prepare(
    'SELECT m.id, m.created_at, m.user_one_id, m.user_two_id
     FROM matches m
     WHERE NOT EXISTS (SELECT 1 FROM likes l WHERE l.liker_id = m.user_one_id AND l.liked_id = m.user_two_id)
        OR NOT EXISTS (SELECT 1 FROM likes l WHERE l.liker_id = m.user_two_id AND l.liked_id = m.user_one_id)'
);
$badMatches->execute();
$incoherent = 0;
foreach ($badMatches->fetchAll(\PDO::FETCH_ASSOC) as $m) {
    $one = (int) $m['user_one_id'];
    $two = (int) $m['user_two_id'];
    $ts = (new \DateTimeImmutable((string) $m['created_at']))->format('Y-m-d H:i:s');

    foreach ([[$one, $two], [$two, $one]] as [$liker, $liked]) {
        $likeStmt->execute([':liker' => $liker, ':liked' => $liked, ':created_at' => $ts]);
        $notifyStmt->execute([
            ':user_id' => $liked,
            ':type' => 'like',
            ':actor_id' => $liker,
            ':created_at' => $ts,
            ':read_at' => $ts,
        ]);
    }
    foreach ([[$one, $two], [$two, $one]] as [$receiver, $actor]) {
        $notifyStmt->execute([
            ':user_id' => $receiver,
            ':type' => 'match',
            ':actor_id' => $actor,
            ':created_at' => $ts,
            ':read_at' => $ts,
        ]);
    }
    $scoreStmt->execute([':id' => $one]);
    $scoreStmt->execute([':id' => $two]);
    $incoherent++;
}

// --- 2. Likes mutuels sans match : création du match (hors blocages et passes)
$mutualNoMatch = $pdo->prepare(
    'SELECT l1.liker_id AS a, l1.liked_id AS b, GREATEST(l1.created_at, l2.created_at) AS matched_at
     FROM likes l1
     JOIN likes l2 ON l2.liker_id = l1.liked_id AND l2.liked_id = l1.liker_id
     WHERE l1.liker_id < l1.liked_id
       AND NOT EXISTS (
           SELECT 1 FROM matches m
           WHERE (m.user_one_id = l1.liker_id AND m.user_two_id = l1.liked_id)
       )
       AND NOT EXISTS (
           SELECT 1 FROM blocks bk
           WHERE (bk.blocker_id = l1.liker_id AND bk.blocked_id = l1.liked_id)
              OR (bk.blocker_id = l1.liked_id AND bk.blocked_id = l1.liker_id)
       )
       AND NOT EXISTS (SELECT 1 FROM passes pa WHERE pa.user_id = l1.liker_id AND pa.passed_id = l1.liked_id)'
);
$mutualNoMatch->execute();
$createdMatches = 0;
foreach ($mutualNoMatch->fetchAll(\PDO::FETCH_ASSOC) as $row) {
    $a = (int) $row['a'];
    $b = (int) $row['b'];
    $ts = (new \DateTimeImmutable((string) $row['matched_at']))->format('Y-m-d H:i:s');
    $matchStmt->execute([':a' => $a, ':b' => $b, ':created_at' => $ts]);
    if ($matchStmt->rowCount() === 0) {
        continue;
    }
    $notifyStmt->execute([':user_id' => $b, ':type' => 'match', ':actor_id' => $a, ':created_at' => $ts, ':read_at' => $ts]);
    $notifyStmt->execute([':user_id' => $a, ':type' => 'match', ':actor_id' => $b, ':created_at' => $ts, ':read_at' => $ts]);
    $scoreStmt->execute([':id' => $a]);
    $scoreStmt->execute([':id' => $b]);
    $createdMatches++;
}

// --- 3. Rapport final
$report = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM matches m WHERE NOT EXISTS (SELECT 1 FROM likes l WHERE l.liker_id = m.user_one_id AND l.liked_id = m.user_two_id) OR NOT EXISTS (SELECT 1 FROM likes l WHERE l.liker_id = m.user_two_id AND l.liked_id = m.user_one_id)) AS matchs_incoherents,
        (SELECT COUNT(*) FROM likes l1 JOIN likes l2 ON l2.liker_id = l1.liked_id AND l2.liked_id = l1.liker_id WHERE l1.liker_id < l1.liked_id AND NOT EXISTS (SELECT 1 FROM matches m WHERE m.user_one_id = l1.liker_id AND m.user_two_id = l1.liked_id)) AS mutuels_sans_match"
)->fetch(\PDO::FETCH_ASSOC);

$pdo->commit();

echo "Matchs réparés (likes manquants créés) : {$incoherent}\n";
echo "Matchs créés depuis des likes mutuels : {$createdMatches}\n";
echo "Vérification — matchs incohérents restants : {$report['matchs_incoherents']} | likes mutuels sans match : {$report['mutuels_sans_match']}\n";
