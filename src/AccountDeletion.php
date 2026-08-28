<?php

declare(strict_types=1);

namespace FeedMySheep;

use PDO;
use RuntimeException;

/**
 * Permanently removes an account and everything attached to it.
 *
 * Most of the schema already cascades from users(id), so notes, highlights,
 * bookmarks, settings, sessions and reading progress disappear on their own.
 * Three foreign keys deliberately do not, because losing them silently would
 * destroy other people's work:
 *
 *   groups.owner_user_id             RESTRICT
 *   reading_plans.created_by_user_id RESTRICT
 *   group_plans.assigned_by_user_id  RESTRICT
 *
 * Those three are what this class resolves before the row is deleted. A group
 * with other people still in it is handed to one of them rather than destroyed:
 * one member leaving should not take a family's shared history with them. A
 * group with nobody else in it has nothing to hand over, so it goes.
 */
final class AccountDeletion
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @return array{groups_transferred: list<string>, groups_deleted: list<string>}
     */
    public function delete(int $userId, string $password): array
    {
        $this->verifyPassword($userId, $password);

        $this->database->beginTransaction();
        try {
            $summary = $this->resolveOwnedGroups($userId);
            $this->reassignRemainingAssignments($userId);
            $this->resolveRemainingPlans($userId);

            $this->database->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            $this->database->commit();
            return $summary;
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    private function verifyPassword(int $userId, string $password): void
    {
        $query = $this->database->prepare("SELECT password_hash FROM users WHERE id = ? AND status = 'active'");
        $query->execute([$userId]);
        $hash = $query->fetchColumn();

        if (!is_string($hash) || !password_verify($password, $hash)) {
            throw new RuntimeException('password_incorrect');
        }
    }

    /**
     * Hands over every group this user owns, or deletes the ones nobody else is in.
     *
     * @return array{groups_transferred: list<string>, groups_deleted: list<string>}
     */
    private function resolveOwnedGroups(int $userId): array
    {
        $owned = $this->database->prepare('SELECT id, name FROM groups WHERE owner_user_id = ?');
        $owned->execute([$userId]);

        $transferred = [];
        $deleted = [];

        foreach ($owned->fetchAll() as $group) {
            $groupId = (int) $group['id'];
            $successor = $this->findSuccessor($groupId, $userId);

            if ($successor === null) {
                $this->deleteGroup($groupId, $userId);
                $deleted[] = (string) $group['name'];
                continue;
            }

            $this->transferGroup($groupId, $userId, $successor);
            $transferred[] = (string) $group['name'];
        }

        return ['groups_transferred' => $transferred, 'groups_deleted' => $deleted];
    }

    /** The longest-standing remaining member, preferring an existing admin. */
    private function findSuccessor(int $groupId, int $userId): ?int
    {
        $query = $this->database->prepare(
            "SELECT user_id FROM group_members
             WHERE group_id = ? AND user_id <> ? AND status = 'active'
             ORDER BY FIELD(role, 'admin', 'member', 'owner'), joined_at ASC, user_id ASC
             LIMIT 1"
        );
        $query->execute([$groupId, $userId]);
        $successor = $query->fetchColumn();

        return $successor === false ? null : (int) $successor;
    }

    private function transferGroup(int $groupId, int $userId, int $successor): void
    {
        $this->database->prepare('UPDATE groups SET owner_user_id = ? WHERE id = ?')
            ->execute([$successor, $groupId]);

        $this->database->prepare("UPDATE group_members SET role = 'owner' WHERE group_id = ? AND user_id = ?")
            ->execute([$groupId, $successor]);

        // The departing member keeps no trace on the plans they wrote, but the
        // plans themselves stay exactly where the group can still read them.
        $this->database->prepare(
            'UPDATE reading_plans rp
             JOIN group_plans gp ON gp.plan_id = rp.id
             SET rp.created_by_user_id = ?
             WHERE gp.group_id = ? AND rp.created_by_user_id = ?'
        )->execute([$successor, $groupId, $userId]);

        $this->database->prepare(
            'UPDATE group_plans SET assigned_by_user_id = ? WHERE group_id = ? AND assigned_by_user_id = ?'
        )->execute([$successor, $groupId, $userId]);
    }

    /** Removes a group nobody else belongs to, along with the plans only it used. */
    private function deleteGroup(int $groupId, int $userId): void
    {
        // group_plans cascades from the group, which would leave these plans
        // orphaned and still holding a reference to the departing user.
        $plans = $this->database->prepare('SELECT plan_id FROM group_plans WHERE group_id = ?');
        $plans->execute([$groupId]);
        $planIds = $plans->fetchAll(PDO::FETCH_COLUMN);

        $this->database->prepare('DELETE FROM groups WHERE id = ?')->execute([$groupId]);

        $orphaned = $this->database->prepare(
            'DELETE rp FROM reading_plans rp
             LEFT JOIN group_plans gp ON gp.plan_id = rp.id
             WHERE rp.id = ? AND gp.plan_id IS NULL'
        );
        foreach ($planIds as $planId) {
            $orphaned->execute([(int) $planId]);
        }
    }

    /**
     * Plans in groups this user did not own still record who assigned them.
     * That reference passes to whoever owns the group now.
     */
    private function reassignRemainingAssignments(int $userId): void
    {
        $this->database->prepare(
            'UPDATE group_plans gp
             JOIN groups g ON g.id = gp.group_id
             SET gp.assigned_by_user_id = g.owner_user_id
             WHERE gp.assigned_by_user_id = ?'
        )->execute([$userId]);
    }

    /**
     * Anything this user wrote that a group is still reading passes to that
     * group's owner. A plan no group uses belongs to nobody and is deleted.
     */
    private function resolveRemainingPlans(int $userId): void
    {
        $this->database->prepare(
            'UPDATE reading_plans rp
             JOIN group_plans gp ON gp.plan_id = rp.id
             JOIN groups g ON g.id = gp.group_id
             SET rp.created_by_user_id = g.owner_user_id
             WHERE rp.created_by_user_id = ?'
        )->execute([$userId]);

        $this->database->prepare(
            'DELETE rp FROM reading_plans rp
             LEFT JOIN group_plans gp ON gp.plan_id = rp.id
             WHERE rp.created_by_user_id = ? AND gp.plan_id IS NULL'
        )->execute([$userId]);
    }
}
