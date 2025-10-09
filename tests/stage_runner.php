#!/usr/bin/env php
<?php
declare(strict_types=1);

$rootDir = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', $rootDir . DIRECTORY_SEPARATOR);
}

if ($argc < 2) {
    fwrite(STDERR, "Uso: stage_runner.php <stage> [--opciones]\n");
    exit(1);
}

$stage = $argv[1];
$args  = parse_args(array_slice($argv, 2));

try {
    switch ($stage) {
        case '01-fetch':
            require_once $rootDir . '/server-mirror/compu-import-lego/includes/stages/01-fetch.php';
            $runner = new Compu_Stage_Fetch();
            $runner->run($args);
            break;
        case '02-normalize':
            require_once $rootDir . '/server-mirror/compu-import-lego/includes/stages/02-normalize.php';
            $runner = new Compu_Stage_Normalize();
            $runner->run($args);
            break;
        case '03-validate':
            require_once $rootDir . '/server-mirror/compu-import-lego/includes/stages/03-validate.php';
            $runner = new Compu_Stage_Validate();
            $runner->run($args);
            break;
        default:
            throw new RuntimeException("Stage desconocido: {$stage}");
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

function parse_args(array $input): array
{
    $result = [];
    $count  = count($input);
    for ($i = 0; $i < $count; $i++) {
        $token = $input[$i];
        if (strncmp($token, '--', 2) !== 0) {
            continue;
        }
        $token = substr($token, 2);
        if ($token === '') {
            continue;
        }
        $parts = explode('=', $token, 2);
        if (count($parts) === 2) {
            $result[$parts[0]] = $parts[1];
            continue;
        }
        $key = $parts[0];
        $nextIndex = $i + 1;
        if ($nextIndex < $count && strncmp($input[$nextIndex], '--', 2) !== 0) {
            $result[$key] = $input[$nextIndex];
            $i++;
        } else {
            $result[$key] = true;
        }
    }
    return $result;
}
