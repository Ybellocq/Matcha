<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\Mailer;
use App\Services\NotificationService;
use App\Middleware\AuthMiddleware;
use function App\db;
use function App\jsonResponse;
use function App\getSanitizedBody;
use function App\getSanitizedQuery;
use function App\requireFields;
use function App\hashToken;
use function App\ensureCsrfToken;
use function App\nowUtc;
use function App\currentUserId;
use function App\isCommonPassword;
use function App\sanitizeString;

return function (App $app, Mailer $mailer, NotificationService $notifications): void {
    $app->get('/health', function (Request $request, Response $response): Response {
        return jsonResponse($response, ['status' => 'ok']);
    });

    $app->get('/auth/csrf', function (Request $request, Response $response): Response {
        return jsonResponse($response, ['csrfToken' => ensureCsrfToken()]);
    });

    $app->post('/auth/register', function (Request $request, Response $response) use ($mailer): Response {
        $data = getSanitizedBody($request);
        $missing = requireFields($data, ['email', 'username', 'first_name', 'last_name', 'password']);
        if ($missing !== []) {
            return jsonResponse($response, ['error' => 'Missing fields', 'fields' => $missing], 422);
        }

        $email = strtolower((string) $data['email']);
        $username = strtolower((string) $data['username']);
        $password = (string) $data['password'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return jsonResponse($response, ['error' => 'Invalid email'], 422);
        }
        if (strlen($password) < 8 || isCommonPassword($password)) {
            return jsonResponse($response, ['error' => 'Weak password'], 422);
        }

        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR username = :username');
        $stmt->execute([':email' => $email, ':username' => $username]);
        if ($stmt->fetch()) {
            return jsonResponse($response, ['error' => 'Email or username already used'], 409);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, username, first_name, last_name, password_hash, is_verified, created_at, last_seen_at)
             VALUES (:email, :username, :first_name, :last_name, :password_hash, false, :created_at, :last_seen_at)
             RETURNING id'
        );
        $stmt->execute([
            ':email' => $email,
            ':username' => $username,
            ':first_name' => sanitizeString((string) $data['first_name']),
            ':last_name' => sanitizeString((string) $data['last_name']),
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ':created_at' => nowUtc(),
            ':last_seen_at' => nowUtc()
        ]);
        $userId = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO profiles (user_id, orientation, created_at, updated_at)
             VALUES (:user_id, :orientation, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':orientation' => 'bisexual',
            ':created_at' => nowUtc(),
            ':updated_at' => nowUtc()
        ]);

        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare(
            'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, :created_at)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => hashToken($token),
            ':expires_at' => gmdate('Y-m-d H:i:s', time() + 60 * 60 * 24),
            ':created_at' => nowUtc()
        ]);
        $pdo->commit();

        $link = rtrim((string)($_ENV['FRONTEND_ORIGIN'] ?? ''), '/') . '/verify?token=' . urlencode($token);
        $mailer->send($email, 'Verify your Matcha account', '<p>Click to verify:</p><p>' . $link . '</p>');

        return jsonResponse($response, ['status' => 'registered']);
    });

    $app->post('/auth/verify', function (Request $request, Response $response): Response {
        $data = getSanitizedBody($request);
        $missing = requireFields($data, ['token']);
        if ($missing !== []) {
            return jsonResponse($response, ['error' => 'Missing token'], 422);
        }
        $tokenHash = hashToken((string) $data['token']);

        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT id, user_id, expires_at, used_at
             FROM email_verification_tokens
             WHERE token_hash = :hash'
        );
        $stmt->execute([':hash' => $tokenHash]);
        $row = $stmt->fetch();
        if (!$row) {
            return jsonResponse($response, ['error' => 'Invalid token'], 404);
        }
        if ($row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
            return jsonResponse($response, ['error' => 'Token expired'], 410);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE users SET is_verified = true WHERE id = :id');
        $stmt->execute([':id' => $row['user_id']]);
        $stmt = $pdo->prepare('UPDATE email_verification_tokens SET used_at = :used_at WHERE id = :id');
        $stmt->execute([':used_at' => nowUtc(), ':id' => $row['id']]);
        $pdo->commit();

        return jsonResponse($response, ['status' => 'verified']);
    });

    $app->post('/auth/login', function (Request $request, Response $response): Response {
        $data = getSanitizedBody($request);
        $missing = requireFields($data, ['username', 'password']);
        if ($missing !== []) {
            return jsonResponse($response, ['error' => 'Missing fields', 'fields' => $missing], 422);
        }

        $stmt = db()->prepare('SELECT id, password_hash, is_verified FROM users WHERE username = :username');
        $stmt->execute([':username' => strtolower((string) $data['username'])]);
        $user = $stmt->fetch();
        if (!$user || !password_verify((string) $data['password'], (string) $user['password_hash'])) {
            return jsonResponse($response, ['error' => 'Invalid credentials'], 401);
        }
        if (!$user['is_verified']) {
            return jsonResponse($response, ['error' => 'Email not verified'], 403);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        ensureCsrfToken();

        $stmt = db()->prepare('UPDATE users SET last_login_at = :last_login, last_seen_at = :last_seen WHERE id = :id');
        $stmt->execute([
            ':last_login' => nowUtc(),
            ':last_seen' => nowUtc(),
            ':id' => $user['id']
        ]);

        return jsonResponse($response, ['status' => 'logged_in']);
    });

    $app->post('/auth/password/request', function (Request $request, Response $response) use ($mailer): Response {
        $data = getSanitizedBody($request);
        $missing = requireFields($data, ['email']);
        if ($missing !== []) {
            return jsonResponse($response, ['error' => 'Missing email'], 422);
        }
        $email = strtolower((string) $data['email']);
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user) {
            return jsonResponse($response, ['status' => 'ok']);
        }

        $token = bin2hex(random_bytes(32));
        $stmt = db()->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, :created_at)'
        );
        $stmt->execute([
            ':user_id' => $user['id'],
            ':token_hash' => hashToken($token),
            ':expires_at' => gmdate('Y-m-d H:i:s', time() + 60 * 60),
            ':created_at' => nowUtc()
        ]);

        $link = rtrim((string)($_ENV['FRONTEND_ORIGIN'] ?? ''), '/') . '/reset-password?token=' . urlencode($token);
        $mailer->send($email, 'Reset your Matcha password', '<p>Reset link:</p><p>' . $link . '</p>');

        return jsonResponse($response, ['status' => 'sent']);
    });

    $app->post('/auth/password/reset', function (Request $request, Response $response): Response {
        $data = getSanitizedBody($request);
        $missing = requireFields($data, ['token', 'password']);
        if ($missing !== []) {
            return jsonResponse($response, ['error' => 'Missing fields'], 422);
        }
        $password = (string) $data['password'];
        if (strlen($password) < 8 || isCommonPassword($password)) {
            return jsonResponse($response, ['error' => 'Weak password'], 422);
        }

        $tokenHash = hashToken((string) $data['token']);
        $stmt = db()->prepare(
            'SELECT id, user_id, expires_at, used_at
             FROM password_reset_tokens
             WHERE token_hash = :hash'
        );
        $stmt->execute([':hash' => $tokenHash]);
        $row = $stmt->fetch();
        if (!$row) {
            return jsonResponse($response, ['error' => 'Invalid token'], 404);
        }
        if ($row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
            return jsonResponse($response, ['error' => 'Token expired'], 410);
        }

        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([
            ':hash' => password_hash($password, PASSWORD_BCRYPT),
            ':id' => $row['user_id']
        ]);
        $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used_at = :used_at WHERE id = :id');
        $stmt->execute([':used_at' => nowUtc(), ':id' => $row['id']]);
        $pdo->commit();

        return jsonResponse($response, ['status' => 'password_updated']);
    });

    $app->get('/auth/me', function (Request $request, Response $response): Response {
        $userId = currentUserId();
        if ($userId === null) {
            return jsonResponse($response, ['user' => null, 'photos' => [], 'tags' => []]);
        }
        
        $stmt = db()->prepare(
            'SELECT u.id, u.email, u.username, u.first_name, u.last_name, u.last_seen_at,
                    p.gender, p.orientation, p.bio, p.location_city, p.location_lat, p.location_lng,
                    p.birthdate, p.popularity_score, p.main_photo_id
             FROM users u
             JOIN profiles p ON p.user_id = u.id
             WHERE u.id = :id'
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        $photos = db()->prepare('SELECT id, path, is_main FROM photos WHERE user_id = :id ORDER BY is_main DESC, id ASC');
        $photos->execute([':id' => $userId]);
        $tags = db()->prepare(
            'SELECT t.name FROM tags t
             JOIN user_tags ut ON ut.tag_id = t.id
             WHERE ut.user_id = :id'
        );
        $tags->execute([':id' => $userId]);

        return jsonResponse($response, [
            'user' => $user,
            'photos' => $photos->fetchAll(),
            'tags' => array_column($tags->fetchAll(), 'name'),
            'csrfToken' => ensureCsrfToken()
        ]);
    });

    $app->group('', function (RouteCollectorProxy $group) use ($notifications): void {
        $group->post('/auth/logout', function (Request $request, Response $response): Response {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            return jsonResponse($response, ['status' => 'logged_out']);
        });

        $group->put('/profile/me', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $data = getSanitizedBody($request);

            $allowedUser = ['email', 'first_name', 'last_name'];
            $allowedProfile = ['gender', 'orientation', 'bio', 'location_city', 'location_lat', 'location_lng', 'birthdate'];

            $userFields = array_intersect_key($data, array_flip($allowedUser));
            $profileFields = array_intersect_key($data, array_flip($allowedProfile));

            $pdo = db();

            if (isset($userFields['email']) && !filter_var($userFields['email'], FILTER_VALIDATE_EMAIL)) {
                return jsonResponse($response, ['error' => 'Invalid email'], 422);
            }
            if (isset($userFields['email'])) {
                $check = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
                $check->execute([':email' => strtolower((string) $userFields['email']), ':id' => $userId]);
                if ($check->fetch()) {
                    return jsonResponse($response, ['error' => 'Email already used'], 409);
                }
                $userFields['email'] = strtolower((string) $userFields['email']);
            }

            if ($userFields !== []) {
                $sets = [];
                $params = [':id' => $userId];
                foreach ($userFields as $key => $value) {
                    $sets[] = $key . ' = :' . $key;
                    $params[':' . $key] = sanitizeString((string) $value);
                }
                $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $stmt->execute($params);
            }

            if ($profileFields !== []) {
                $sets = [];
                $params = [':user_id' => $userId];
                foreach ($profileFields as $key => $value) {
                    if (in_array($key, ['location_lat', 'location_lng'], true) && ($value === '' || $value === null)) {
                        $value = null;
                    }
                    $sets[] = $key . ' = :' . $key;
                    $params[':' . $key] = $value;
                }
                $sets[] = 'updated_at = :updated_at';
                $params[':updated_at'] = nowUtc();
                $stmt = $pdo->prepare('UPDATE profiles SET ' . implode(', ', $sets) . ' WHERE user_id = :user_id');
                $stmt->execute($params);
            }

            if (isset($data['tags']) && is_array($data['tags'])) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('DELETE FROM user_tags WHERE user_id = :id');
                $stmt->execute([':id' => $userId]);
                foreach ($data['tags'] as $tag) {
                    $tagName = strtolower((string) sanitizeString((string) $tag));
                    $tagName = ltrim($tagName, '#');
                    if ($tagName === '') {
                        continue;
                    }
                    $stmt = $pdo->prepare('SELECT id FROM tags WHERE name = :name');
                    $stmt->execute([':name' => $tagName]);
                    $tagId = $stmt->fetchColumn();
                    if (!$tagId) {
                        $stmt = $pdo->prepare('INSERT INTO tags (name) VALUES (:name) RETURNING id');
                        $stmt->execute([':name' => $tagName]);
                        $tagId = $stmt->fetchColumn();
                    }
                    $stmt = $pdo->prepare('INSERT INTO user_tags (user_id, tag_id) VALUES (:user_id, :tag_id)');
                    $stmt->execute([':user_id' => $userId, ':tag_id' => $tagId]);
                }
                $pdo->commit();
            }

            return jsonResponse($response, ['status' => 'updated']);
        });

        $group->get('/profile/me', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $stmt = db()->prepare(
                'SELECT u.id, u.username, u.first_name, u.last_name, u.email,
                        p.gender, p.orientation, p.bio, p.location_city, p.location_lat, p.location_lng,
                        p.birthdate, p.popularity_score, p.main_photo_id
                 FROM users u
                 JOIN profiles p ON p.user_id = u.id
                 WHERE u.id = :id'
            );
            $stmt->execute([':id' => $userId]);
            $profile = $stmt->fetch();

            $photos = db()->prepare('SELECT id, path, is_main FROM photos WHERE user_id = :id ORDER BY is_main DESC, id ASC');
            $photos->execute([':id' => $userId]);
            $tags = db()->prepare(
                'SELECT t.name FROM tags t
                 JOIN user_tags ut ON ut.tag_id = t.id
                 WHERE ut.user_id = :id'
            );
            $tags->execute([':id' => $userId]);

            return jsonResponse($response, [
                'profile' => $profile,
                'photos' => $photos->fetchAll(),
                'tags' => array_column($tags->fetchAll(), 'name')
            ]);
        });

        $group->get('/profile/views', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $stmt = db()->prepare(
                'SELECT u.username, u.first_name, u.last_name, v.created_at
                 FROM profile_views v
                 JOIN users u ON u.id = v.viewer_id
                 WHERE v.viewed_id = :id
                 ORDER BY v.created_at DESC'
            );
            $stmt->execute([':id' => $userId]);
            return jsonResponse($response, ['views' => $stmt->fetchAll()]);
        });

        $group->get('/profile/likes', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $stmt = db()->prepare(
                'SELECT u.username, u.first_name, u.last_name, l.created_at
                 FROM likes l
                 JOIN users u ON u.id = l.liker_id
                 WHERE l.liked_id = :id
                 ORDER BY l.created_at DESC'
            );
            $stmt->execute([':id' => $userId]);
            return jsonResponse($response, ['likes' => $stmt->fetchAll()]);
        });

        $group->get('/profile/blocked', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $stmt = db()->prepare(
                'SELECT u.username, u.first_name, u.last_name, b.created_at
                 FROM blocks b
                 JOIN users u ON u.id = b.blocked_id
                 WHERE b.blocker_id = :id
                 ORDER BY b.created_at DESC'
            );
            $stmt->execute([':id' => $userId]);
            return jsonResponse($response, ['blocked' => $stmt->fetchAll()]);
        });

        $group->get('/profile/{username}', function (Request $request, Response $response, array $args) use ($notifications): Response {
            $userId = currentUserId();
            $username = strtolower((string) $args['username']);

            $stmt = db()->prepare(
                'SELECT u.id, u.username, u.first_name, u.last_name,
                        p.gender, p.orientation, p.bio, p.location_city, p.location_lat, p.location_lng,
                        p.birthdate, p.popularity_score, p.main_photo_id, u.last_seen_at
                 FROM users u
                 JOIN profiles p ON p.user_id = u.id
                 WHERE u.username = :username'
            );
            $stmt->execute([':username' => $username]);
            $profile = $stmt->fetch();
            if (!$profile) {
                return jsonResponse($response, ['error' => 'Profile not found'], 404);
            }

            if ((int) $profile['id'] !== $userId) {
                $stmt = db()->prepare(
                    'INSERT INTO profile_views (viewer_id, viewed_id, created_at)
                     VALUES (:viewer_id, :viewed_id, :created_at)'
                );
                $stmt->execute([
                    ':viewer_id' => $userId,
                    ':viewed_id' => $profile['id'],
                    ':created_at' => nowUtc()
                ]);
                $notifications->notify((int) $profile['id'], 'view', $userId);
                updatePopularityScore((int) $profile['id']);
            }

            $photos = db()->prepare('SELECT id, path, is_main FROM photos WHERE user_id = :id ORDER BY is_main DESC, id ASC');
            $photos->execute([':id' => $profile['id']]);

            $tags = db()->prepare(
                'SELECT t.name FROM tags t
                 JOIN user_tags ut ON ut.tag_id = t.id
                 WHERE ut.user_id = :id'
            );
            $tags->execute([':id' => $profile['id']]);

            $likeStmt = db()->prepare('SELECT 1 FROM likes WHERE liker_id = :me AND liked_id = :other');
            $likeStmt->execute([':me' => $userId, ':other' => $profile['id']]);
            $liked = (bool) $likeStmt->fetchColumn();

            $matchStmt = db()->prepare(
                'SELECT 1 FROM matches WHERE (user_one_id = :me AND user_two_id = :other)
                   OR (user_one_id = :other AND user_two_id = :me)'
            );
            $matchStmt->execute([':me' => $userId, ':other' => $profile['id']]);
            $matched = (bool) $matchStmt->fetchColumn();

            return jsonResponse($response, [
                'profile' => $profile,
                'photos' => $photos->fetchAll(),
                'tags' => array_column($tags->fetchAll(), 'name'),
                'liked' => $liked,
                'matched' => $matched
            ]);
        });

        $group->post('/profile/{username}/like', function (Request $request, Response $response, array $args) use ($notifications): Response {
            $userId = currentUserId();
            $username = strtolower((string) $args['username']);

            $stmt = db()->prepare('SELECT id FROM users WHERE username = :username');
            $stmt->execute([':username' => $username]);
            $targetId = $stmt->fetchColumn();
            if (!$targetId || (int) $targetId === $userId) {
                return jsonResponse($response, ['error' => 'Invalid target'], 404);
            }

            $blockStmt = db()->prepare(
                'SELECT 1 FROM blocks WHERE (blocker_id = :me AND blocked_id = :other)
                   OR (blocker_id = :other AND blocked_id = :me)'
            );
            $blockStmt->execute([':me' => $userId, ':other' => $targetId]);
            if ($blockStmt->fetch()) {
                return jsonResponse($response, ['error' => 'Action not allowed'], 403);
            }

            $photoStmt = db()->prepare('SELECT COUNT(*) FROM photos WHERE user_id = :id');
            $photoStmt->execute([':id' => $userId]);
            if ((int) $photoStmt->fetchColumn() === 0) {
                return jsonResponse($response, ['error' => 'Upload a photo before liking'], 403);
            }

            $stmt = db()->prepare('SELECT 1 FROM likes WHERE liker_id = :me AND liked_id = :other');
            $stmt->execute([':me' => $userId, ':other' => $targetId]);
            if (!$stmt->fetch()) {
                $stmt = db()->prepare(
                    'INSERT INTO likes (liker_id, liked_id, created_at)
                     VALUES (:me, :other, :created_at)'
                );
                $stmt->execute([':me' => $userId, ':other' => $targetId, ':created_at' => nowUtc()]);
                $notifications->notify((int) $targetId, 'like', $userId);
                updatePopularityScore((int) $targetId);
            }

            $matched = false;
            $matchStmt = db()->prepare('SELECT 1 FROM likes WHERE liker_id = :other AND liked_id = :me');
            $matchStmt->execute([':me' => $userId, ':other' => $targetId]);
            if ($matchStmt->fetch()) {
                $a = min($userId, (int) $targetId);
                $b = max($userId, (int) $targetId);
                $stmt = db()->prepare('SELECT 1 FROM matches WHERE user_one_id = :a AND user_two_id = :b');
                $stmt->execute([':a' => $a, ':b' => $b]);
                if (!$stmt->fetch()) {
                    $stmt = db()->prepare(
                        'INSERT INTO matches (user_one_id, user_two_id, created_at)
                         VALUES (:a, :b, :created_at)'
                    );
                    $stmt->execute([':a' => $a, ':b' => $b, ':created_at' => nowUtc()]);
                    $notifications->notify((int) $targetId, 'match', $userId);
                    $notifications->notify($userId, 'match', (int) $targetId);
                    updatePopularityScore((int) $targetId);
                    updatePopularityScore($userId);
                    $matched = true;
                }
            }

            return jsonResponse($response, ['status' => 'liked', 'matched' => $matched]);
        });

        $group->post('/profile/{username}/unlike', function (Request $request, Response $response, array $args) use ($notifications): Response {
            $userId = currentUserId();
            $username = strtolower((string) $args['username']);
            $stmt = db()->prepare('SELECT id FROM users WHERE username = :username');
            $stmt->execute([':username' => $username]);
            $targetId = $stmt->fetchColumn();
            if (!$targetId) {
                return jsonResponse($response, ['error' => 'Invalid target'], 404);
            }

            $stmt = db()->prepare('DELETE FROM likes WHERE liker_id = :me AND liked_id = :other');
            $stmt->execute([':me' => $userId, ':other' => $targetId]);

            $a = min($userId, (int) $targetId);
            $b = max($userId, (int) $targetId);
            $stmt = db()->prepare('DELETE FROM matches WHERE user_one_id = :a AND user_two_id = :b');
            if ($stmt->execute([':a' => $a, ':b' => $b]) && $stmt->rowCount() > 0) {
                $notifications->notify((int) $targetId, 'unlike', $userId);
                updatePopularityScore((int) $targetId);
                updatePopularityScore($userId);
            }

            return jsonResponse($response, ['status' => 'unliked']);
        });

        $group->post('/profile/{username}/block', function (Request $request, Response $response, array $args): Response {
            $userId = currentUserId();
            $username = strtolower((string) $args['username']);
            $stmt = db()->prepare('SELECT id FROM users WHERE username = :username');
            $stmt->execute([':username' => $username]);
            $targetId = $stmt->fetchColumn();
            if (!$targetId) {
                return jsonResponse($response, ['error' => 'Invalid target'], 404);
            }

            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO blocks (blocker_id, blocked_id, created_at)
                 VALUES (:me, :other, :created_at)
                 ON CONFLICT DO NOTHING'
            );
            $stmt->execute([':me' => $userId, ':other' => $targetId, ':created_at' => nowUtc()]);

            $stmt = $pdo->prepare('DELETE FROM likes WHERE (liker_id = :me AND liked_id = :other) OR (liker_id = :other AND liked_id = :me)');
            $stmt->execute([':me' => $userId, ':other' => $targetId]);

            $a = min($userId, (int) $targetId);
            $b = max($userId, (int) $targetId);
            $stmt = $pdo->prepare('DELETE FROM matches WHERE user_one_id = :a AND user_two_id = :b');
            $stmt->execute([':a' => $a, ':b' => $b]);

            $stmt = $pdo->prepare('DELETE FROM messages WHERE (sender_id = :me AND receiver_id = :other) OR (sender_id = :other AND receiver_id = :me)');
            $stmt->execute([':me' => $userId, ':other' => $targetId]);
            $pdo->commit();

            return jsonResponse($response, ['status' => 'blocked']);
        });

        $group->post('/profile/{username}/report', function (Request $request, Response $response, array $args): Response {
            $userId = currentUserId();
            $username = strtolower((string) $args['username']);
            $data = getSanitizedBody($request);
            $reason = sanitizeString((string) ($data['reason'] ?? ''));

            $stmt = db()->prepare('SELECT id FROM users WHERE username = :username');
            $stmt->execute([':username' => $username]);
            $targetId = $stmt->fetchColumn();
            if (!$targetId) {
                return jsonResponse($response, ['error' => 'Invalid target'], 404);
            }

            $stmt = db()->prepare(
                'INSERT INTO reports (reporter_id, reported_id, reason, created_at)
                 VALUES (:reporter, :reported, :reason, :created_at)'
            );
            $stmt->execute([
                ':reporter' => $userId,
                ':reported' => $targetId,
                ':reason' => $reason,
                ':created_at' => nowUtc()
            ]);

            return jsonResponse($response, ['status' => 'reported']);
        });

        $group->post('/photos', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $files = $request->getUploadedFiles();
            if (!isset($files['photo'])) {
                return jsonResponse($response, ['error' => 'Photo required'], 422);
            }
            $photo = $files['photo'];
            if ($photo->getError() !== UPLOAD_ERR_OK) {
                return jsonResponse($response, ['error' => 'Upload failed'], 422);
            }
            $tempPath = $photo->getStream()->getMetadata('uri');
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $type = $finfo ? finfo_file($finfo, (string) $tempPath) : $photo->getClientMediaType();
            if ($finfo) {
                finfo_close($finfo);
            }
            if (!in_array($type, ['image/jpeg', 'image/png'], true)) {
                return jsonResponse($response, ['error' => 'Invalid file type'], 422);
            }
            if ($photo->getSize() > 5 * 1024 * 1024) {
                return jsonResponse($response, ['error' => 'File too large'], 422);
            }

            $uploadDir = __DIR__ . '/../../public/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = $type === 'image/png' ? 'png' : 'jpg';
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            $path = $uploadDir . '/' . $filename;
            $photo->moveTo($path);

            $stmt = db()->prepare('SELECT COUNT(*) FROM photos WHERE user_id = :id');
            $stmt->execute([':id' => $userId]);
            $photoCount = (int) $stmt->fetchColumn();
            if ($photoCount >= 5) {
                return jsonResponse($response, ['error' => 'Maximum 5 photos allowed'], 422);
            }
            $isMain = ($photoCount === 0);

            $stmt = db()->prepare(
                'INSERT INTO photos (user_id, path, is_main, created_at)
                 VALUES (:user_id, :path, :is_main, :created_at)
                 RETURNING id'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':path' => '/uploads/' . $filename,
                ':is_main' => $isMain,
                ':created_at' => nowUtc()
            ]);
            $photoId = (int) $stmt->fetchColumn();
            if ($isMain) {
                db()->prepare('UPDATE profiles SET main_photo_id = :photo_id WHERE user_id = :user_id')
                    ->execute([':photo_id' => $photoId, ':user_id' => $userId]);
            }

            return jsonResponse($response, ['status' => 'uploaded']);
        });

        $group->delete('/photos/{id}', function (Request $request, Response $response, array $args): Response {
            $userId = currentUserId();
            $photoId = (int) $args['id'];
            $stmt = db()->prepare('SELECT path, is_main FROM photos WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $photoId, ':user_id' => $userId]);
            $photo = $stmt->fetch();
            if (!$photo) {
                return jsonResponse($response, ['error' => 'Photo not found'], 404);
            }

            $stmt = db()->prepare('DELETE FROM photos WHERE id = :id');
            $stmt->execute([':id' => $photoId]);
            $filePath = __DIR__ . '/../../public' . $photo['path'];
            if (is_file($filePath)) {
                unlink($filePath);
            }

            if ($photo['is_main']) {
                $stmt = db()->prepare('SELECT id FROM photos WHERE user_id = :user_id ORDER BY id ASC LIMIT 1');
                $stmt->execute([':user_id' => $userId]);
                $newMainId = $stmt->fetchColumn();
                if ($newMainId) {
                    $stmt = db()->prepare('UPDATE photos SET is_main = true WHERE id = :id');
                    $stmt->execute([':id' => $newMainId]);
                    db()->prepare('UPDATE profiles SET main_photo_id = :photo_id WHERE user_id = :user_id')
                        ->execute([':photo_id' => $newMainId, ':user_id' => $userId]);
                } else {
                    db()->prepare('UPDATE profiles SET main_photo_id = NULL WHERE user_id = :user_id')
                        ->execute([':user_id' => $userId]);
                }
            }

            return jsonResponse($response, ['status' => 'deleted']);
        });

        $group->patch('/photos/{id}/main', function (Request $request, Response $response, array $args): Response {
            $userId = currentUserId();
            $photoId = (int) $args['id'];
            $stmt = db()->prepare('SELECT id FROM photos WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $photoId, ':user_id' => $userId]);
            if (!$stmt->fetch()) {
                return jsonResponse($response, ['error' => 'Photo not found'], 404);
            }
            db()->prepare('UPDATE photos SET is_main = false WHERE user_id = :user_id')->execute([':user_id' => $userId]);
            db()->prepare('UPDATE photos SET is_main = true WHERE id = :id')->execute([':id' => $photoId]);
            db()->prepare('UPDATE profiles SET main_photo_id = :photo_id WHERE user_id = :user_id')
                ->execute([':photo_id' => $photoId, ':user_id' => $userId]);
            return jsonResponse($response, ['status' => 'updated']);
        });

        $group->get('/matches', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $stmt = db()->prepare(
                'SELECT u.username, u.first_name, u.last_name, p.main_photo_id, p.popularity_score, u.last_seen_at,
                        ph.path AS main_photo_path
                 FROM matches m
                 JOIN users u ON (u.id = CASE WHEN m.user_one_id = :me THEN m.user_two_id ELSE m.user_one_id END)
                 JOIN profiles p ON p.user_id = u.id
                 LEFT JOIN photos ph ON ph.id = p.main_photo_id
                 WHERE m.user_one_id = :me OR m.user_two_id = :me'
            );
            $stmt->execute([':me' => $userId]);
            return jsonResponse($response, ['matches' => $stmt->fetchAll()]);
        });

        $group->get('/messages/unread', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $stmt = db()->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = :id AND read_at IS NULL');
            $stmt->execute([':id' => $userId]);
            return jsonResponse($response, ['count' => (int) $stmt->fetchColumn()]);
        });

        $group->get('/messages/{username}', function (Request $request, Response $response, array $args): Response {
            $userId = currentUserId();
            $username = strtolower((string) $args['username']);
            $stmt = db()->prepare('SELECT id FROM users WHERE username = :username');
            $stmt->execute([':username' => $username]);
            $targetId = $stmt->fetchColumn();
            if (!$targetId) {
                return jsonResponse($response, ['error' => 'User not found'], 404);
            }
            $a = min($userId, (int) $targetId);
            $b = max($userId, (int) $targetId);
            $matchStmt = db()->prepare('SELECT 1 FROM matches WHERE user_one_id = :a AND user_two_id = :b');
            $matchStmt->execute([':a' => $a, ':b' => $b]);
            if (!$matchStmt->fetch()) {
                return jsonResponse($response, ['error' => 'Not matched'], 403);
            }

            $stmt = db()->prepare(
                'SELECT id, sender_id, receiver_id, body, created_at
                 FROM messages
                 WHERE (sender_id = :me AND receiver_id = :other)
                    OR (sender_id = :other AND receiver_id = :me)
                 ORDER BY created_at ASC'
            );
            $stmt->execute([':me' => $userId, ':other' => $targetId]);

            db()->prepare('UPDATE messages SET read_at = :read_at WHERE receiver_id = :me AND sender_id = :other AND read_at IS NULL')
                ->execute([':read_at' => nowUtc(), ':me' => $userId, ':other' => $targetId]);

            return jsonResponse($response, ['messages' => $stmt->fetchAll()]);
        });

        $group->post('/messages/{username}', function (Request $request, Response $response, array $args) use ($notifications): Response {
            $userId = currentUserId();
            $username = strtolower((string) $args['username']);
            $data = getSanitizedBody($request);
            $missing = requireFields($data, ['body']);
            if ($missing !== []) {
                return jsonResponse($response, ['error' => 'Missing body'], 422);
            }
            $stmt = db()->prepare('SELECT id FROM users WHERE username = :username');
            $stmt->execute([':username' => $username]);
            $targetId = $stmt->fetchColumn();
            if (!$targetId) {
                return jsonResponse($response, ['error' => 'User not found'], 404);
            }
            $a = min($userId, (int) $targetId);
            $b = max($userId, (int) $targetId);
            $matchStmt = db()->prepare('SELECT 1 FROM matches WHERE user_one_id = :a AND user_two_id = :b');
            $matchStmt->execute([':a' => $a, ':b' => $b]);
            if (!$matchStmt->fetch()) {
                return jsonResponse($response, ['error' => 'Not matched'], 403);
            }

            $stmt = db()->prepare(
                'INSERT INTO messages (sender_id, receiver_id, body, created_at)
                 VALUES (:sender, :receiver, :body, :created_at)'
            );
            $stmt->execute([
                ':sender' => $userId,
                ':receiver' => $targetId,
                ':body' => sanitizeString((string) $data['body']),
                ':created_at' => nowUtc()
            ]);
            $notifications->notify((int) $targetId, 'message', $userId);

            return jsonResponse($response, ['status' => 'sent']);
        });

        $group->get('/notifications', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $stmt = db()->prepare(
                'SELECT n.id, n.type, n.actor_id, n.metadata, n.created_at, n.read_at, u.username AS actor_username
                 FROM notifications n
                 JOIN users u ON u.id = n.actor_id
                 WHERE n.user_id = :id
                 ORDER BY n.created_at DESC'
            );
            $stmt->execute([':id' => $userId]);
            return jsonResponse($response, ['notifications' => $stmt->fetchAll()]);
        });

        $group->get('/notifications/unread', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :id AND read_at IS NULL');
            $stmt->execute([':id' => $userId]);
            return jsonResponse($response, ['count' => (int) $stmt->fetchColumn()]);
        });

        $group->post('/notifications/{id}/read', function (Request $request, Response $response, array $args): Response {
            $userId = currentUserId();
            $id = (int) $args['id'];
            $stmt = db()->prepare('UPDATE notifications SET read_at = :read_at WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':read_at' => nowUtc(), ':id' => $id, ':user_id' => $userId]);
            return jsonResponse($response, ['status' => 'read']);
        });

        $group->get('/search/suggestions', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $query = getSanitizedQuery($request);

            $stmt = db()->prepare(
                'SELECT p.user_id, u.username, u.first_name, u.last_name, p.gender, p.orientation, p.birthdate,
                        p.location_lat, p.location_lng, p.location_city, p.popularity_score, p.main_photo_id,
                        ph.path AS main_photo_path
                 FROM profiles p
                 JOIN users u ON u.id = p.user_id
                 LEFT JOIN photos ph ON ph.id = p.main_photo_id
                 WHERE u.id != :id
                   AND u.id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = :id)
                   AND u.id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = :id)'
            );
            $stmt->execute([':id' => $userId]);
            $candidates = $stmt->fetchAll();

            $meStmt = db()->prepare('SELECT gender, orientation, location_lat, location_lng FROM profiles WHERE user_id = :id');
            $meStmt->execute([':id' => $userId]);
            $me = $meStmt->fetch();

            $tagStmt = db()->prepare('SELECT t.name FROM tags t JOIN user_tags ut ON ut.tag_id = t.id WHERE ut.user_id = :id');
            $tagStmt->execute([':id' => $userId]);
            $myTags = array_column($tagStmt->fetchAll(), 'name');

            $results = [];
            foreach ($candidates as $candidate) {
                $compatible = true;
                if ($me) {
                    $myGender = $me['gender'] ?? '';
                    $myOrientation = $me['orientation'] ?? 'bisexual';
                    $otherGender = $candidate['gender'] ?? '';
                    $otherOrientation = $candidate['orientation'] ?? 'bisexual';

                    $compatible = orientationCompatible($myGender, $myOrientation, $otherGender, $otherOrientation);
                }
                if (!$compatible) {
                    continue;
                }

                $distance = null;
                if ($me && $me['location_lat'] !== null && $me['location_lng'] !== null
                    && $candidate['location_lat'] !== null && $candidate['location_lng'] !== null) {
                    $distance = haversine(
                        (float) $me['location_lat'],
                        (float) $me['location_lng'],
                        (float) $candidate['location_lat'],
                        (float) $candidate['location_lng']
                    );
                }

                $tagStmt = db()->prepare(
                    'SELECT t.name FROM tags t JOIN user_tags ut ON ut.tag_id = t.id WHERE ut.user_id = :id'
                );
                $tagStmt->execute([':id' => $candidate['user_id']]);
                $theirTags = array_column($tagStmt->fetchAll(), 'name');
                $sharedTags = count(array_intersect($myTags, $theirTags));

                $score = (int) $candidate['popularity_score'] + ($sharedTags * 10);
                if ($distance !== null) {
                    $score += max(0, 100 - (int) $distance);
                }

                $results[] = [
                    'profile' => $candidate,
                    'sharedTags' => $sharedTags,
                    'tags' => $theirTags,
                    'distanceKm' => $distance,
                    'score' => $score
                ];
            }

            usort($results, function (array $a, array $b): int {
                return $b['score'] <=> $a['score'];
            });

            return jsonResponse($response, ['results' => $results]);
        });

        $group->get('/search/advanced', function (Request $request, Response $response): Response {
            $userId = currentUserId();
            $query = getSanitizedQuery($request);
            $ageMin = isset($query['age_min']) ? (int) $query['age_min'] : null;
            $ageMax = isset($query['age_max']) ? (int) $query['age_max'] : null;
            $popMin = isset($query['popularity_min']) ? (int) $query['popularity_min'] : null;
            $popMax = isset($query['popularity_max']) ? (int) $query['popularity_max'] : null;
            $tags = [];
            if (isset($query['tags'])) {
                $tags = is_array($query['tags'])
                    ? $query['tags']
                    : array_filter(array_map('trim', explode(',', (string) $query['tags'])));
            }
            $distanceMax = isset($query['distance_km']) ? (float) $query['distance_km'] : null;
            $locationCity = isset($query['location']) ? strtolower((string) $query['location']) : null;

            $stmt = db()->prepare(
                'SELECT p.user_id, u.username, u.first_name, u.last_name, p.gender, p.orientation, p.birthdate,
                        p.location_lat, p.location_lng, p.location_city, p.popularity_score, p.main_photo_id,
                        ph.path AS main_photo_path
                 FROM profiles p
                 JOIN users u ON u.id = p.user_id
                 LEFT JOIN photos ph ON ph.id = p.main_photo_id
                 WHERE u.id != :id
                   AND u.id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = :id)
                   AND u.id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = :id)'
            );
            $stmt->execute([':id' => $userId]);
            $candidates = $stmt->fetchAll();

            $meStmt = db()->prepare('SELECT location_lat, location_lng FROM profiles WHERE user_id = :id');
            $meStmt->execute([':id' => $userId]);
            $me = $meStmt->fetch();

            $results = [];
            foreach ($candidates as $candidate) {
                if ($locationCity && $candidate['location_city']) {
                    if (stripos((string) $candidate['location_city'], $locationCity) === false) {
                        continue;
                    }
                }
                if ($popMin !== null && (int) $candidate['popularity_score'] < $popMin) {
                    continue;
                }
                if ($popMax !== null && (int) $candidate['popularity_score'] > $popMax) {
                    continue;
                }
                if ($candidate['birthdate']) {
                    $age = (int) floor((time() - strtotime($candidate['birthdate'])) / 31557600);
                    if ($ageMin !== null && $age < $ageMin) {
                        continue;
                    }
                    if ($ageMax !== null && $age > $ageMax) {
                        continue;
                    }
                }

                if ($distanceMax !== null) {
                    if (!$me || $me['location_lat'] === null || $me['location_lng'] === null
                        || $candidate['location_lat'] === null || $candidate['location_lng'] === null) {
                        continue;
                    }
                    $distance = haversine(
                        (float) $me['location_lat'],
                        (float) $me['location_lng'],
                        (float) $candidate['location_lat'],
                        (float) $candidate['location_lng']
                    );
                    if ($distance > $distanceMax) {
                        continue;
                    }
                }

                if ($tags !== []) {
                    $tagStmt = db()->prepare(
                        'SELECT t.name FROM tags t JOIN user_tags ut ON ut.tag_id = t.id WHERE ut.user_id = :id'
                    );
                    $tagStmt->execute([':id' => $candidate['user_id']]);
                    $theirTags = array_column($tagStmt->fetchAll(), 'name');
                    $shared = array_intersect($tags, $theirTags);
                    if (count($shared) === 0) {
                        continue;
                    }
                }

                $results[] = $candidate;
            }

            return jsonResponse($response, ['results' => $results]);
        });
    })->add(new AuthMiddleware());
};

function orientationCompatible(?string $genderA, string $orientationA, ?string $genderB, string $orientationB): bool
{
    return allowsGender($orientationA, $genderA, $genderB) && allowsGender($orientationB, $genderB, $genderA);
}

function allowsGender(string $orientation, ?string $selfGender, ?string $targetGender): bool
{
    if ($orientation === 'bisexual') {
        return true;
    }
    if ($selfGender === null || $targetGender === null) {
        return true;
    }
    if ($orientation === 'heterosexual') {
        return $selfGender !== $targetGender;
    }
    if ($orientation === 'homosexual') {
        return $selfGender === $targetGender;
    }
    return true;
}

function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    $c = 2 * asin(min(1, sqrt($a)));
    return $earth * $c;
}

function updatePopularityScore(int $userId): void
{
    $stmt = db()->prepare(
        'SELECT
            (SELECT COUNT(*) FROM profile_views WHERE viewed_id = :id) AS views,
            (SELECT COUNT(*) FROM likes WHERE liked_id = :id) AS likes,
            (SELECT COUNT(*) FROM matches WHERE user_one_id = :id OR user_two_id = :id) AS matches,
            CASE WHEN (SELECT last_seen_at FROM users WHERE id = :id) > NOW() - INTERVAL \'1 day\' THEN 5 ELSE 1 END AS activity'
    );
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    if ($row) {
        $score = (int) $row['views'] + ((int) $row['likes'] * 2) + ((int) $row['matches'] * 3) + (int) $row['activity'];
        db()->prepare('UPDATE profiles SET popularity_score = :score WHERE user_id = :id')
            ->execute([':score' => $score, ':id' => $userId]);
    }
}
