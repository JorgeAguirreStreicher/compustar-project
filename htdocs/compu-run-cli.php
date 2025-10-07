#!/usr/bin/env php
<?php
declare(strict_types=1);

if (!function_exists('compu_run_exit')) {
    function compu_run_exit(int $code, string $message = ''): void
    {
        if ($message !== '') {
            $stream = $code === 0 ? STDOUT : STDERR;
            fwrite($stream, $message . "\n");
        }
        fflush(STDOUT);
        fflush(STDERR);
        exit($code);
    }
}

if (PHP_SAPI !== 'cli') {
    compu_run_exit(2, 'compu-run-cli.php must be executed from CLI');
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['SERVER_ADDR'] = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/compu-run-cli';
$_SERVER['SERVER_PROTOCOL'] = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
$_SERVER['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'compu-run-cli/1.0';

if (!defined('DOING_CRON')) {
    define('DOING_CRON', true);
}
if (!defined('WP_ADMIN')) {
    define('WP_ADMIN', true);
}
if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

if (!defined('WOODMART_THEME_DIR')) {
    define('WOODMART_THEME_DIR', __DIR__ . '/wp-content/themes/woodmart');
}
if (!defined('WOODMART_THEME_SLUG')) {
    define('WOODMART_THEME_SLUG', 'woodmart');
}
if (!defined('WOODMART_THEME_NAME')) {
    define('WOODMART_THEME_NAME', 'Woodmart');
}

require __DIR__ . '/compu-run.php';
