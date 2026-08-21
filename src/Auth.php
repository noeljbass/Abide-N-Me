<?php

declare(strict_types=1);

namespace FeedMySheep;

use PDO;

final class Auth
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function register(string $name, string $email, string $password): array
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $publicId = self::uuidV4();
        $statement = $this->database->prepare(
            "INSERT INTO users (public_id, name, email, password_hash, status) VALUES (:public_id, :name, :email, :password_hash, 'active')"
        );
        $statement->execute(['public_id' => $publicId, 'name' => $name, 'email' => $email, 'password_hash' => $hash]);
        $userId = (int) $this->database->lastInsertId();
        $settings = $this->database->prepare('INSERT INTO user_settings (user_id) VALUES (:user_id)');
        $settings->execute(['user_id' => $userId]);
        return $this->findById($userId);
    }

    public function attempt(string $email, string $password): ?array
    {
        $statement = $this->database->prepare(
            "SELECT id, public_id, name, email, password_hash, email_verified_at FROM users WHERE email = :email AND status = 'active' LIMIT 1"
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = $this->database->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $rehash->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }
        unset($user['password_hash']);
        return $this->publicUser($user);
    }

    public function requireUser(): array
    {
        $userId = Session::userId();
        if ($userId === null) {
            JsonResponse::error('authentication_required', 'Please sign in to continue.', 401);
        }
        $user = $this->findById($userId);
        if ($user === null) {
            Session::logout();
            JsonResponse::error('authentication_required', 'Please sign in to continue.', 401);
        }
        return $user;
    }

    public function findById(int $id): ?array
    {
        $statement = $this->database->prepare(
            "SELECT id, public_id, name, email, email_verified_at FROM users WHERE id = :id AND status = 'active' LIMIT 1"
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        return $user ? $this->publicUser($user) : null;
    }

    public function updateName(int $id, string $name): array
    {
        $statement = $this->database->prepare('UPDATE users SET name = :name WHERE id = :id');
        $statement->execute(['name' => $name, 'id' => $id]);
        return $this->findById($id);
    }

    private function publicUser(array $user): array
    {
        return [
            'id' => $user['public_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'email_verified' => $user['email_verified_at'] !== null,
        ];
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

