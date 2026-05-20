<?php
declare(strict_types=1);

namespace App\Services;

use function App\db;
use function App\nowUtc;

final class NotificationService
{
    public function notify(int $userId, string $type, int $actorId, array $metadata = []): void
    {
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
