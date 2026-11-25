<?php
declare(strict_types=1);

// Crée de faux matchs avec conversations pour simuler des messages.
// Usage :
//   php db/seed_matches.php                    # cible auto-détectée (1er compte non "userN")
//   php db/seed_matches.php alice@example.com  # cible par e-mail ou username
//   php db/seed_matches.php alice 12           # cible + nombre de matchs (défaut : 8)

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

// --- Cible : argument CLI ou premier utilisateur enregistré (username hors user1..user500)
$targetArg = $argv[1] ?? null;
if ($targetArg !== null) {
    $stmt = $pdo->prepare('SELECT id, username, first_name FROM users WHERE email = :v OR username = :v');
    $stmt->execute([':v' => strtolower(trim($targetArg))]);
    $me = $stmt->fetch();
    if (!$me) {
        fwrite(STDERR, "Aucun utilisateur trouvé pour « {$targetArg} ».\n");
        exit(1);
    }
} else {
    $me = $pdo->query(
        "SELECT id, username, first_name FROM users WHERE email NOT LIKE '%@example.com' AND is_verified ORDER BY created_at DESC LIMIT 1"
    )->fetch();
    if ($me) {
        echo "Cible auto-détectée : {$me['username']} ({$me['first_name']})\n";
    } else {
        // Aucun compte enregistré : démonstration sur le premier utilisateur seedé
        $me = $pdo->query("SELECT id, username, first_name FROM users WHERE email LIKE '%@example.com' ORDER BY id LIMIT 1")->fetch();
        if (!$me) {
            fwrite(STDERR, "Base vide : lance d'abord `php db/seed.php` ou inscris-toi sur le site.\n");
            exit(1);
        }
        echo "Aucun compte enregistré trouvé, démonstration sur : {$me['username']} ({$me['first_name']})\n";
    }
}
$meId = (int) $me['id'];

$count = isset($argv[2]) ? max(1, min(20, (int) $argv[2])) : 8;

// --- Candidats : profils seedés vérifiés, avec photo principale, pas déjà matchés ni passés
$candidates = $pdo->prepare(
    "SELECT u.id, u.first_name
     FROM users u
     JOIN profiles p ON p.user_id = u.id
     LEFT JOIN photos ph ON ph.id = p.main_photo_id
     WHERE u.email LIKE '%@example.com'
       AND u.id <> :me
       AND u.is_verified
       AND ph.id IS NOT NULL
       AND NOT EXISTS (
           SELECT 1 FROM matches m
           WHERE (m.user_one_id = :me AND m.user_two_id = u.id)
              OR (m.user_two_id = :me AND m.user_one_id = u.id)
       )
       AND NOT EXISTS (SELECT 1 FROM passes pa WHERE pa.user_id = :me AND pa.passed_id = u.id)
     ORDER BY random()
     LIMIT :limit"
);
$candidates->bindValue(':me', $meId, \PDO::PARAM_INT);
$candidates->bindValue(':limit', $count, \PDO::PARAM_INT);
$candidates->execute();
$partners = $candidates->fetchAll();

if (!$partners) {
    echo "Tous les candidats ont déjà été matchés ou passés avec ce compte.\n";
    exit(0);
}

// --- Conversations modèles : chaque script = tours alternant [them, me, them, ...]
$conversationScripts = [
    [
        "Salut ! Le sourire sur ta photo principale, c'est contagieux",
        "Haha merci ! Il faut dire qu'elle a été bien choisie",
        "Tu fais quoi dans la vie pour sourire comme ça ?",
        "Graphiste. Je passe mes journées à rendre les choses jolies",
        "Ça se voit, même ta bio est soignée",
        "Et encore, tu n'as vu que la version courte !",
    ],
    [
        "Bon, ton profil m'est apparu trois fois aujourd'hui. Le destin ou l'algorithme ?",
        "L'algorithme, mais acceptons l'idée du destin pour la romance",
        "Café ou thé ? Réponse décisive avant d'aller plus loin",
        "Café, serré. J'assume tous les clichés qui vont avec",
        "Approuvé. On tient notre premier sujet de conversation",
    ],
    [
        "Hey ! On dirait qu'on a 3 tags en commun 🎬",
        "Oui alors, je dois savoir tout de suite : équipe binge-watch ou équipe cinéma en salle ?",
        "Les deux ! Salle le week-end, canapé la semaine",
        "Réponse parfaite, tu as gagné le droit de me poser une question",
        "Tu préfères plutôt montagne ou plage ?",
        "Montagne, sans hésiter. La plage, c'est beaucoup de sable",
    ],
    [
        "Salut ! Ton dernier tag me intrigue, c'est quoi l'histoire derrière ?",
        "Un petit défi lancé par un ami à la base, devenu une vraie passion",
        "Les meilleures histoires commencent toujours comme ça",
        "Et toi, c'est quoi ton truc qui surprend les gens ?",
        "Je cuisine énormément. Mes amis ne s'en plaignent jamais",
        "OK c'est officiel, il faut qu'on organise un dîner de groupe un jour",
    ],
    [
        "Coucou ! J'espère que ta journée était plus douce que le métro ce matin",
        "La barre est haute, mais oui, plutôt bonne journée 😄",
        "Ça me rassure. Ici aussi ça va, entre deux réunions interminables",
        "Le Lifestyle d'un adulte glamour, comme on dit",
        "Glamour c'est le mot. On garde le cap quand même",
    ],
    [
        "Salut toi ! J'aime bien ta bio, très fausse-modeste de prétendre ne pas voyager beaucoup",
        "Elle est passée, la modesterie 😅 Disons que j'ai eu la chance de bouger pas mal",
        "Note pour plus tard : demander la carte du monde et toi tes spots préférés",
        "Le Portugal, sans hésiter. La lumière y est incroyable",
        "Bien noté. Tu notes le Portugal dans ma liste des vacances idéales",
    ],
    [
        "Hey ! Question existentielle : pineapple sur la pizza, oui ou non ?",
        "Non, et je juge gentiment les personnes qui répondent oui",
        "J'adore ton honnêteté, du coup je ne te dirai pas mon avis 😏",
        "Tiens, une énigmatique ! J'aime ça",
        "Prudence. Reste sur ton prochain message, il pourrait tout changer",
    ],
    [
        "Salut ! Vu la distance, on est pourquoi pas voisins. Tu squattes quel quartier ?",
        "Près du canal, les dimanches au marché c'est mon rituel",
        "Ah oui, tout près ! Je passe souvent par là le matin",
        "Le monde est petit. Bon, un de ces quatre, café de quartier ?",
        "Ça part pour ça. En attendant, je continue de te spammer de messages",
    ],
];

$likeStmt = $pdo->prepare(
    'INSERT INTO likes (liker_id, liked_id, created_at) VALUES (:liker, :liked, :created_at) ON CONFLICT DO NOTHING'
);
$matchStmt = $pdo->prepare(
    'INSERT INTO matches (user_one_id, user_two_id, created_at) VALUES (:a, :b, :created_at) ON CONFLICT DO NOTHING'
);
$matchExists = $pdo->prepare('SELECT 1 FROM matches WHERE user_one_id = :a AND user_two_id = :b');
$messageStmt = $pdo->prepare(
    'INSERT INTO messages (sender_id, receiver_id, body, created_at, read_at) VALUES (:sender, :receiver, :body, :created_at, :read_at)'
);
$notifyStmt = $pdo->prepare(
    'INSERT INTO notifications (user_id, type, actor_id, metadata, created_at, read_at) VALUES (:user_id, :type, :actor_id, NULL, :created_at, :read_at)'
);

$now = strtotime(nowUtc());
$createdMatches = 0;

foreach ($partners as $index => $partner) {
    $partnerId = (int) $partner['id'];
    $script = $conversationScripts[$index % count($conversationScripts)];

    // Match il y a 1 à 10 jours
    $matchTs = $now - mt_rand(1, 10) * 86400 - mt_rand(0, 43200);

    $likeStmt->execute([':liker' => $meId, ':liked' => $partnerId, ':created_at' => gmdate('Y-m-d H:i:s', $matchTs - 3600)]);
    $likeStmt->execute([':liker' => $partnerId, ':liked' => $meId, ':created_at' => gmdate('Y-m-d H:i:s', $matchTs - 1800)]);
    $matchStmt->execute([
        ':a' => min($meId, $partnerId),
        ':b' => max($meId, $partnerId),
        ':created_at' => gmdate('Y-m-d H:i:s', $matchTs),
    ]);
    $matchExists->execute([':a' => min($meId, $partnerId), ':b' => max($meId, $partnerId)]);
    if (!$matchExists->fetchColumn()) {
        continue;
    }
    $createdMatches++;
    $createdTs = gmdate('Y-m-d H:i:s', $matchTs);

    // Une conversation sur deux se termine par un message non lu (badge + notification)
    $unread = ($index % 2 === 0);
    $turnsToSend = mt_rand(3, count($script));

    // Génération du fil en mémoire, puis alignement du dernier message sur « maintenant »
    $ts = $matchTs + mt_rand(120, 600);
    $rows = [];
    foreach ($script as $turnIndex => $body) {
        if ($turnIndex >= $turnsToSend) {
            break;
        }
        $themSends = ($turnIndex % 2 === 0);
        $rows[] = [
            'from' => $themSends ? $partnerId : $meId,
            'to' => $themSends ? $meId : $partnerId,
            'body' => $body,
            'ts' => $ts,
            'them' => $themSends,
        ];
        // 2 min à 3 h entre chaque message
        $ts += mt_rand(120, 10800);
    }

    if ($unread) {
        // Dernier message reçu il y a 2 à 120 min : la conversation semble en cours
        $shift = ($now - mt_rand(120, 7200)) - end($rows)['ts'];
    } elseif (end($rows)['ts'] > $now - 60) {
        // Le fil dépasserait « maintenant » (match très récent) : on le fait terminer avant
        $shift = ($now - mt_rand(3600, 86400)) - end($rows)['ts'];
    } else {
        $shift = 0;
    }
    if ($shift > 0) {
        foreach ($rows as &$row) {
            $row['ts'] += $shift;
        }
        unset($row);
    }

    foreach ($rows as $turnIndex => $row) {
        $isLast = ($turnIndex === count($rows) - 1);
        $readAt = ($row['them'] && !$unread) ? gmdate('Y-m-d H:i:s', $row['ts'] + mt_rand(60, 600)) : null;

        $messageStmt->execute([
            ':sender' => $row['from'],
            ':receiver' => $row['to'],
            ':body' => $row['body'],
            ':created_at' => gmdate('Y-m-d H:i:s', $row['ts']),
            ':read_at' => $readAt,
        ]);

        if ($row['them'] && $unread && $isLast && $row['ts'] >= $now - 7200) {
            $notifyStmt->execute([
                ':user_id' => $meId,
                ':type' => 'message',
                ':actor_id' => $partnerId,
                ':created_at' => gmdate('Y-m-d H:i:s', $row['ts']),
                ':read_at' => null,
            ]);
        }
    }

    // Notifications de match (en liné, envoyées au moment du match)
    $notifyStmt->execute([
        ':user_id' => $meId,
        ':type' => 'match',
        ':actor_id' => $partnerId,
        ':created_at' => $createdTs,
        ':read_at' => $unread ? null : gmdate('Y-m-d H:i:s', $matchTs + 3600),
    ]);
    $notifyStmt->execute([
        ':user_id' => $partnerId,
        ':type' => 'match',
        ':actor_id' => $meId,
        ':created_at' => $createdTs,
        ':read_at' => gmdate('Y-m-d H:i:s', $matchTs + 60),
    ]);
}

// Recalcul des scores de popularité (même formule que l'API)
$updateScoreRow = $pdo->prepare(
    "UPDATE profiles SET popularity_score = (
         (SELECT COUNT(*) FROM profile_views v WHERE v.viewed_id = :id)
         + (SELECT COUNT(*) FROM likes l WHERE l.liked_id = :id) * 2
         + (SELECT COUNT(*) FROM matches m WHERE m.user_one_id = :id OR m.user_two_id = :id) * 3
         + CASE WHEN (SELECT last_seen_at FROM users WHERE id = :id) > NOW() - INTERVAL '1 day' THEN 5 ELSE 1 END
     ) WHERE user_id = :id"
);
$updateScoreRow->execute([':id' => $meId]);
foreach ($partners as $partner) {
    $updateScoreRow->execute([':id' => (int) $partner['id']]);
}

echo "{$createdMatches} matchs créés (sur " . count($partners) . " candidats) avec conversations pour {$me['username']}.\n";
