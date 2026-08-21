<?php

declare(strict_types=1);

namespace FeedMySheep;

use PDO;

final class GroupService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function create(int $userId, string $name, ?string $description): array
    {
        $publicId = self::uuidV4();
        $this->database->beginTransaction();
        try {
            $insert = $this->database->prepare('INSERT INTO groups (public_id, owner_user_id, name, description) VALUES (:public_id, :owner, :name, :description)');
            $insert->execute(['public_id' => $publicId, 'owner' => $userId, 'name' => $name, 'description' => $description]);
            $groupId = (int) $this->database->lastInsertId();
            $member = $this->database->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (:group_id, :user_id, 'owner')");
            $member->execute(['group_id' => $groupId, 'user_id' => $userId]);
            $this->database->commit();
            return $this->findForUser($publicId, $userId);
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }

    public function listForUser(int $userId): array
    {
        $query = $this->database->prepare("SELECT g.public_id AS id, g.name, g.description, gm.role, gm.joined_at, COUNT(active.user_id) AS member_count FROM group_members gm JOIN groups g ON g.id = gm.group_id LEFT JOIN group_members active ON active.group_id = g.id AND active.status = 'active' WHERE gm.user_id = :user_id AND gm.status = 'active' AND g.archived_at IS NULL GROUP BY g.id, g.public_id, g.name, g.description, gm.role, gm.joined_at ORDER BY gm.joined_at DESC");
        $query->execute(['user_id' => $userId]);
        return array_map([$this, 'formatGroup'], $query->fetchAll());
    }

    public function findForUser(string $publicId, int $userId): ?array
    {
        $query = $this->database->prepare("SELECT g.id AS internal_id, g.public_id AS id, g.name, g.description, gm.role, gm.joined_at, (SELECT COUNT(*) FROM group_members count_members WHERE count_members.group_id = g.id AND count_members.status = 'active') AS member_count FROM groups g JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = :user_id AND gm.status = 'active' WHERE g.public_id = :public_id AND g.archived_at IS NULL LIMIT 1");
        $query->execute(['public_id' => $publicId, 'user_id' => $userId]);
        $group = $query->fetch();
        return $group ? $this->formatGroup($group) : null;
    }

    public function members(string $publicId, int $userId): array
    {
        $group = $this->authorizedGroup($publicId, $userId);
        $query = $this->database->prepare("SELECT u.public_id AS id, u.name, u.avatar_data AS avatar, gm.role, gm.joined_at FROM group_members gm JOIN users u ON u.id = gm.user_id WHERE gm.group_id = :group_id AND gm.status = 'active' ORDER BY FIELD(gm.role, 'owner', 'admin', 'member'), u.name");
        $query->execute(['group_id' => $group['internal_id']]);
        return $query->fetchAll();
    }

    public function createInvite(string $publicId, int $userId, string $role, ?int $expiresInDays): array
    {
        $group = $this->authorizedGroup($publicId, $userId, ['owner', 'admin']);
        $code = self::inviteCode();
        $expiresAt = $expiresInDays === null ? null : gmdate('Y-m-d H:i:s', time() + ($expiresInDays * 86400));
        $insert = $this->database->prepare('INSERT INTO group_invites (group_id, created_by_user_id, code_hash, code_hint, role, expires_at) VALUES (:group_id, :creator, :hash, :hint, :role, :expires_at)');
        $insert->execute(['group_id' => $group['internal_id'], 'creator' => $userId, 'hash' => hash('sha256', $code), 'hint' => substr($code, -4), 'role' => $role, 'expires_at' => $expiresAt]);
        return ['code' => $code, 'group' => $this->formatGroup($group), 'expires_at' => $expiresAt];
    }

    public function delete(string $publicId, int $userId): void
    {
        $group = $this->authorizedGroup($publicId, $userId, ['owner']);
        $archive = $this->database->prepare('UPDATE groups SET archived_at = UTC_TIMESTAMP() WHERE id = :group_id AND archived_at IS NULL');
        $archive->execute(['group_id' => $group['internal_id']]);
    }

    public function previewInvite(string $code): ?array
    {
        $query = $this->database->prepare("SELECT g.name, g.description, gi.expires_at FROM group_invites gi JOIN groups g ON g.id = gi.group_id WHERE gi.code_hash = :hash AND gi.revoked_at IS NULL AND (gi.expires_at IS NULL OR gi.expires_at > UTC_TIMESTAMP()) AND (gi.max_uses IS NULL OR gi.use_count < gi.max_uses) AND g.archived_at IS NULL LIMIT 1");
        $query->execute(['hash' => hash('sha256', self::normalizeCode($code))]);
        $invite = $query->fetch();
        return $invite ?: null;
    }

    public function join(string $code, int $userId): array
    {
        $hash = hash('sha256', self::normalizeCode($code));
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare("SELECT gi.id, gi.group_id, gi.role, g.public_id FROM group_invites gi JOIN groups g ON g.id = gi.group_id WHERE gi.code_hash = :hash AND gi.revoked_at IS NULL AND (gi.expires_at IS NULL OR gi.expires_at > UTC_TIMESTAMP()) AND (gi.max_uses IS NULL OR gi.use_count < gi.max_uses) AND g.archived_at IS NULL FOR UPDATE");
            $query->execute(['hash' => $hash]);
            $invite = $query->fetch();
            if (!$invite) {
                $this->database->rollBack();
                return [];
            }
            $existing = $this->database->prepare('SELECT status FROM group_members WHERE group_id = :group_id AND user_id = :user_id');
            $existing->execute(['group_id' => $invite['group_id'], 'user_id' => $userId]);
            $status = $existing->fetchColumn();
            if ($status === false) {
                $insert = $this->database->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (:group_id, :user_id, :role)');
                $insert->execute(['group_id' => $invite['group_id'], 'user_id' => $userId, 'role' => $invite['role']]);
                $this->database->prepare('UPDATE group_invites SET use_count = use_count + 1 WHERE id = :id')->execute(['id' => $invite['id']]);
            } elseif ($status !== 'active') {
                $this->database->prepare("UPDATE group_members SET status = 'active', role = :role, joined_at = UTC_TIMESTAMP() WHERE group_id = :group_id AND user_id = :user_id")->execute(['role' => $invite['role'], 'group_id' => $invite['group_id'], 'user_id' => $userId]);
                $this->database->prepare('UPDATE group_invites SET use_count = use_count + 1 WHERE id = :id')->execute(['id' => $invite['id']]);
            }
            $this->database->commit();
            return $this->findForUser($invite['public_id'], $userId) ?? [];
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $exception;
        }
    }

    public function userInternalId(string $publicUserId): ?int
    {
        $query = $this->database->prepare("SELECT id FROM users WHERE public_id = :id AND status = 'active'");
        $query->execute(['id' => $publicUserId]);
        $id = $query->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function changeRole(string $publicId, int $actorId, string $memberPublicId, string $role): void
    {
        $group = $this->authorizedGroup($publicId, $actorId, ['owner']);
        $memberId = $this->userInternalId($memberPublicId);
        if ($memberId === null || $memberId === $actorId) JsonResponse::error('invalid_member', 'That member cannot be changed.', 422);
        $update = $this->database->prepare("UPDATE group_members SET role = :role WHERE group_id = :group_id AND user_id = :user_id AND status = 'active' AND role <> 'owner'");
        $update->execute(['role' => $role, 'group_id' => $group['internal_id'], 'user_id' => $memberId]);
        if ($update->rowCount() === 0) JsonResponse::error('member_not_found', 'That member was not found.', 404);
    }

    public function removeMember(string $publicId, int $actorId, string $memberPublicId): void
    {
        $group = $this->authorizedGroup($publicId, $actorId, ['owner', 'admin']);
        $memberId = $this->userInternalId($memberPublicId);
        if ($memberId === null || $memberId === $actorId) JsonResponse::error('invalid_member', 'That member cannot be removed.', 422);
        $remove = $this->database->prepare("UPDATE group_members SET status = 'removed' WHERE group_id = :group_id AND user_id = :user_id AND status = 'active' AND role <> 'owner'");
        $remove->execute(['group_id' => $group['internal_id'], 'user_id' => $memberId]);
        if ($remove->rowCount() === 0) JsonResponse::error('member_not_found', 'That member was not found.', 404);
    }

    public function requireInternalUserId(string $publicUserId): int
    {
        $id = $this->userInternalId($publicUserId);
        if ($id === null) JsonResponse::error('authentication_required', 'Please sign in to continue.', 401);
        return $id;
    }

    private function authorizedGroup(string $publicId, int $userId, array $roles = ['owner', 'admin', 'member']): array
    {
        $query = $this->database->prepare("SELECT g.id AS internal_id, g.public_id AS id, g.name, g.description, gm.role, gm.joined_at, (SELECT COUNT(*) FROM group_members x WHERE x.group_id = g.id AND x.status = 'active') AS member_count FROM groups g JOIN group_members gm ON gm.group_id = g.id WHERE g.public_id = :public_id AND gm.user_id = :user_id AND gm.status = 'active' AND g.archived_at IS NULL LIMIT 1");
        $query->execute(['public_id' => $publicId, 'user_id' => $userId]);
        $group = $query->fetch();
        if (!$group || !in_array($group['role'], $roles, true)) JsonResponse::error('group_not_found', 'That group was not found.', 404);
        return $group;
    }

    private function formatGroup(array $group): array
    {
        unset($group['internal_id']);
        $group['member_count'] = (int) $group['member_count'];
        return $group;
    }

    public static function normalizeCode(string $code): string { return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code)); }
    private static function inviteCode(): string { $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $code = ''; for ($i = 0; $i < 12; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)]; return $code; }
    private static function uuidV4(): string { $b = random_bytes(16); $b[6] = chr((ord($b[6]) & 15) | 64); $b[8] = chr((ord($b[8]) & 63) | 128); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4)); }
}
