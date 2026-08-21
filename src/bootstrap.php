<?php

declare(strict_types=1);

use FeedMySheep\Config;
use FeedMySheep\Logger;

spl_autoload_register(static function (string $class): void {
    $prefix = 'FeedMySheep\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$config = Config::load(dirname(__DIR__));
date_default_timezone_set((string) $config->get('app.timezone', 'UTC'));

$debug = $config->get('app.debug', false) === true;
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$logger = new Logger(dirname(__DIR__) . '/storage/logs/app.log');
set_exception_handler(static function (Throwable $exception) use ($logger, $debug): void {
    $logger->error('Uncaught exception', [
        'type' => $exception::class,
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    $message = $debug ? $exception->getMessage() : 'An unexpected error occurred.';
    echo json_encode(['success' => false, 'data' => null, 'error' => ['code' => 'server_error', 'message' => $message]], JSON_UNESCAPED_SLASHES);
});

return ['config' => $config, 'logger' => $logger];

