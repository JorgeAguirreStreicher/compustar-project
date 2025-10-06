<?php
declare(strict_types=1);

// Parse CLI arguments into $_GET for convenience when executed via CLI.
if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    foreach (array_slice($argv, 1) as $arg) {
        if (strpos($arg, '=') !== false) {
            [$key, $value] = explode('=', $arg, 2);
            $_GET[$key] = $value;
        }
    }
}

$expectedToken = getenv('COMPU_RUN_TOKEN');
if ($expectedToken === false || $expectedToken === '') {
    $expectedToken = 'dev-compustar-token';
}

$providedToken = isset($_GET['token']) ? (string) $_GET['token'] : '';
if (!hash_equals($expectedToken, $providedToken)) {
    echo "[auth] INVALID_TOKEN\n";
    exit(99);
}

$runId = isset($_GET['run_id']) ? trim((string) $_GET['run_id']) : '';
if ($runId === '') {
    echo "[runner] MISSING_RUN_ID\n";
    exit(98);
}

$stage = isset($_GET['stage']) ? (string) $_GET['stage'] : '';
if ($stage === '') {
    echo "[runner] MISSING_STAGE\n";
    exit(97);
}

switch ($stage) {
    case '01':
        // === stage 01: preparar source.csv desde copia local (provisional) ===
        if (isset($_GET['stage']) && $_GET['stage'] === '01') {
            $runDir = isset($_GET['run_dir']) ? rtrim((string) $_GET['run_dir'], '/') : null;
            $src    = '/home/compustar/ProductosHora.csv'; // INSUMO PROVISIONAL
            if (!$runDir || !is_dir($runDir)) {
                echo "[stage01] INVALID_RUN_DIR\n";
                exit(1);
            }
            if (!is_readable($src)) {
                echo "[stage01] SOURCE_MISSING $src\n";
                exit(2);
            }
            $dst = $runDir . '/source.csv';
            // Copiar (sobrescribe si ya existe para idempotencia de pruebas)
            if (!@copy($src, $dst)) {
                echo "[stage01] COPY_FAILED $src -> $dst\n";
                exit(3);
            }
            @chmod($dst, 0664);
            // Si el proceso no tiene permisos para chown/chgrp, ignora errores
            @chown($dst, 'compustar'); @chgrp($dst, 'compustar');
            echo "[stage01] SOURCE_READY $dst\n";
            exit(0);
        }
        break;
    case '02':
        echo "[stage02] NOT_IMPLEMENTED\n";
        exit(96);
    case '03':
    case '04':
    case '05':
    case '06':
    case '07':
    case '08':
    case '09':
    case '10':
    case '11':
        echo "[stage{$stage}] NOT_IMPLEMENTED\n";
        exit(95);
    default:
        echo "[runner] UNKNOWN_STAGE {$stage}\n";
        exit(94);
}
