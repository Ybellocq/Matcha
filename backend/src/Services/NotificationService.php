<?php

declare(strict_types=1);

namespace App\Services;

use function App\db;
use function App\nowUtc;

final class NotificationService
{
    public function notify(int $userId, string $type, int $actorId, array $metadata = []): void
    {
        // A blocked user must never generate notifications in either direction.
        $blockStmt = db()->prepare(
            'SELECT 1 FROM blocks
             WHERE (blocker_id = :a AND blocked_id = :b)
                OR (blocker_id = :b AND blocked_id = :a)
             LIMIT 1'
        );
        $blockStmt->execute([':a' => $userId, ':b' => $actorId]);
        if ($blockStmt->fetch()) {
            return;
        }

        $stmt = db()->prepare(
            'INSERT INTO notifications (user_id, type, actor_id, metadata, created_at)
             VALUES (:user_id, :type, :actor_id, :metadata, :created_at)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':actor_id' => $actorId,
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            ':created_at' => nowUtc(),
        ]);
    }
}
