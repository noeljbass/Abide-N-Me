<?php

declare(strict_types=1);

namespace FeedMySheep;

use PDO;

final class RateLimiter
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function hit(string $action, string $subject, int $limit, int $windowSeconds): void
    {
        $subjectHash = hash('sha256', $subject);
        $this->database->beginTransaction();
        try {
            $select = $this->database->prepare('SELECT attempts, window_started_at, blocked_until FROM rate_limits WHERE action = :action AND subject_hash = :subject_hash FOR UPDATE');
            $select->execute(['action' => $action, 'subject_hash' => $subjectHash]);
            $record = $select->fetch();
            $now = time();
            if ($record && $record['blocked_until'] !== null && strtotime($record['blocked_until']) > $now) {
                $this->database->commit();
                JsonResponse::error('rate_limited', 'Too many attempts. Please wait and try again.', 429);
            }

            $windowExpired = !$record || strtotime($record['window_started_at']) <= $now - $windowSeconds;
            $attempts = $windowExpired ? 1 : ((int) $record['attempts'] + 1);
            $blockedUntil = $attempts > $limit ? gmdate('Y-m-d H:i:s', $now + $windowSeconds) : null;
            $upsert = $this->database->prepare(
                'INSERT INTO rate_limits (action, subject_hash, window_started_at, attempts, blocked_until) VALUES (:action, :subject_hash, UTC_TIMESTAMP(), :attempts, :blocked_until) ON DUPLICATE KEY UPDATE window_started_at = IF(:reset_window = 1, UTC_TIMESTAMP(), window_started_at), attempts = :attempts_update, blocked_until = :blocked_until_update'
            );
            $upsert->execute([
                'action' => $action, 'subject_hash' => $subjectHash, 'attempts' => $attempts,
                'blocked_until' => $blockedUntil, 'reset_window' => $windowExpired ? 1 : 0,
                'attempts_update' => $attempts, 'blocked_until_update' => $blockedUntil,
            ]);
            $this->database->commit();
            if ($blockedUntil !== null) {
                JsonResponse::error('rate_limited', 'Too many attempts. Please wait and try again.', 429);
            }
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }
}

