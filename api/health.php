<?php

declare(strict_types=1);

use FeedMySheep\Database;
use FeedMySheep\JsonResponse;

$app = require dirname(__DIR__) . '/src/bootstrap.php';
$requiredTables = ['users', 'user_settings', 'auth_tokens', 'rate_limits'];

try {
    $pdo = (new Database($app['config']))->connection();
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $statement = $pdo->query('SHOW TABLES');
    $tables = $statement->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_values(array_diff($requiredTables, $tables));
    if ($missing !== []) {
        JsonResponse::error('schema_incomplete', 'The database is connected but required authentication tables are missing.', 503, ['missing_tables' => $missing]);
    }
    JsonResponse::success(['database' => 'connected', 'schema' => 'ready', 'server' => str_contains(strtolower($version), 'mariadb') ? 'MariaDB' : 'MySQL']);
} catch (Throwable $exception) {
    $app['logger']->error('Database health check failed', ['type' => $exception::class, 'message' => $exception->getMessage()]);
    $code = str_contains($exception->getMessage(), 'configuration') ? 'database_configuration_missing' : 'database_connection_failed';
    JsonResponse::error($code, 'The application cannot connect to its database. Check the private server configuration and database access.', 503);
}
