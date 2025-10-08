#!/usr/bin/env php
<?php
declare(strict_types=1);

use CompuImport\Kernel\StageKernel;

const COMPU_RUN_ALLOWED_STAGES = ['01', '02', '03', '04', '06'];

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

function compu_run_help_text(): string
{
    return <<<TEXT
Compu Import unified runner
Usage:
  php compu-run.php --stages=01..06 [options]

Options:
  --stages=LIST          Stage list (e.g. 01..06 or 01,02,03,04,06)
  --dry-run=0|1          Skip execution and only prepare run context
  --require-term=0|1     Require taxonomy term mapping (propagated to stages)
  --limit=N              Limit records processed by applicable stages
  --offset=N             Skip first N records (stage 06)
  --from=N               Start subset at row N (alias of --subset-from)
  --rows=N               Number of rows for subset (alias of --subset-rows)
  --csv=PATH             Source CSV file to link as source.csv
  --run-base=PATH        Override RUN_BASE directory
  --run-dir=PATH         Reuse an existing run directory
  --run-id=VALUE         Provide a numeric run identifier (otherwise generated)
  --wp-root=PATH         Override WordPress root
  --plugin-dir=PATH      Override plugin directory
  --wp-cli=PATH          Path to wp binary (default /usr/local/bin/wp)
  --php-bin=PATH         PHP binary for sub-process stages (default PHP_BINARY)
  --help                 Show this message
TEXT;
}

function compu_run_parse_args(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }
        if (substr($arg, 0, 2) !== '--') {
            continue;
        }
        $arg = substr($arg, 2);
        if (strpos($arg, '=') !== false) {
            [$key, $value] = explode('=', $arg, 2);
        } else {
            $key = $arg;
            $value = '1';
        }
        $options[strtolower(str_replace('-', '_', $key))] = $value;
    }
    return $options;
}

function compu_run_expand_stages(string $spec): array
{
    $spec = trim($spec);
    if ($spec === '') {
        return [];
    }

    if (preg_match('/^\d{2}\.\.\d{2}$/', $spec)) {
        [$start, $end] = explode('..', $spec, 2);
        $startInt = (int) $start;
        $endInt = (int) $end;
        if ($startInt <= 0 || $endInt <= 0 || $startInt > $endInt) {
            return [];
        }
        $result = [];
        foreach (COMPU_RUN_ALLOWED_STAGES as $stage) {
            $value = (int) $stage;
            if ($value >= $startInt && $value <= $endInt) {
                $result[] = $stage;
            }
        }
        return $result;
    }

    $result = [];
    foreach (explode(',', $spec) as $item) {
        $item = trim($item);
        if ($item === '') {
            continue;
        }
        if (!preg_match('/^\d{2}$/', $item)) {
            return [];
        }
        if (!in_array($item, COMPU_RUN_ALLOWED_STAGES, true)) {
            return [];
        }
        if (!in_array($item, $result, true)) {
            $result[] = $item;
        }
    }

    return $result;
}

function compu_run_info(string $message): void
{
    fwrite(STDOUT, '[compu-run] ' . $message . "\n");
    fflush(STDOUT);
}

function compu_run_generate_run_id(): string
{
    $timestamp = gmdate('YmdHis');
    try {
        $random = random_int(0, 999);
    } catch (Throwable $e) {
        $random = mt_rand(0, 999);
    }
    return $timestamp . str_pad((string) $random, 3, '0', STR_PAD_LEFT);
}

function compu_run_normalize_run_id(?string $value): string
{
    if ($value === null || $value === '') {
        return compu_run_generate_run_id();
    }
    $digits = preg_replace('/\D+/', '', (string) $value);
    if ($digits === '') {
        compu_run_exit(2, 'Provided --run-id must contain at least one digit.');
    }
    return $digits;
}

function compu_run_resolve_plugin_dir(string $dir): string
{
    $dir = rtrim($dir, DIRECTORY_SEPARATOR);
    if ($dir !== '' && is_dir($dir)) {
        return $dir;
    }
    $fallback = realpath(__DIR__ . '/../server-mirror/compu-import-lego');
    if ($fallback && is_dir($fallback)) {
        return rtrim($fallback, DIRECTORY_SEPARATOR);
    }
    return $dir;
}

function compu_run_require_file(string $path, string $description): void
{
    if (!is_file($path)) {
        compu_run_exit(2, "Missing required {$description}: {$path}");
    }
    require_once $path;
}

function compu_run_require_plugin_bootstrap(string $pluginDir): void
{
    $helperFiles = [
        '/includes/helpers/helpers-common.php',
        '/includes/helpers/helpers-db.php',
        '/includes/compu-guards.php',
    ];
    foreach ($helperFiles as $helper) {
        compu_run_require_file($pluginDir . $helper, 'plugin helper');
    }

    $kernelFiles = [
        '/includes/kernel/StageInterface.php',
        '/includes/kernel/StageResult.php',
        '/includes/kernel/RunLogger.php',
        '/includes/kernel/stages/Stage01.php',
        '/includes/kernel/stages/Stage02.php',
        '/includes/kernel/stages/Stage03.php',
        '/includes/kernel/stages/Stage04.php',
        '/includes/kernel/stages/Stage06.php',
        '/includes/kernel/StageKernel.php',
    ];
    foreach ($kernelFiles as $file) {
        compu_run_require_file($pluginDir . $file, 'kernel file');
    }

    $stageScripts = [
        '/includes/stages/01-fetch.php',
        '/includes/stages/02-normalize.php',
        '/includes/stages/03-validate.php',
        '/includes/stages/04-resolve-map.php',
    ];
    foreach ($stageScripts as $script) {
        compu_run_require_file($pluginDir . $script, 'stage script');
    }
}

function compu_run_prepare_context(array $options, string $runId, ?string $csvPath): array
{
    $context = [
        'RUN_ID' => $runId,
        'RUN_DB_ID' => (int) $runId,
        'DRY_RUN' => 0,
    ];

    if (isset($options['dry_run'])) {
        $context['DRY_RUN'] = (int) ((int) $options['dry_run'] !== 0);
    }

    $map = [
        'require_term' => 'REQUIRE_TERM',
        'limit' => 'LIMIT',
        'offset' => 'OFFSET',
        'from' => 'SUBSET_FROM',
        'rows' => 'SUBSET_ROWS',
        'subset_from' => 'SUBSET_FROM',
        'subset_rows' => 'SUBSET_ROWS',
        'sample600' => 'SAMPLE600',
        'run_dir' => 'RUN_DIR',
    ];

    foreach ($map as $optionKey => $contextKey) {
        if (!array_key_exists($optionKey, $options)) {
            continue;
        }
        $value = $options[$optionKey];
        if (in_array($contextKey, ['REQUIRE_TERM', 'LIMIT', 'OFFSET', 'SUBSET_FROM', 'SUBSET_ROWS', 'SAMPLE600'], true)) {
            $context[$contextKey] = (int) $value;
        } else {
            $context[$contextKey] = rtrim((string) $value, DIRECTORY_SEPARATOR);
        }
    }

    if ($csvPath !== null && $csvPath !== '') {
        $context['CSV_SRC'] = $csvPath;
    }

    if (!empty($options['source_master'])) {
        $context['SOURCE_MASTER'] = $options['source_master'];
    }

    return $context;
}

function compu_run_append_runlog(string $runDir, string $message): void
{
    $runDir = rtrim($runDir, DIRECTORY_SEPARATOR);
    if ($runDir === '') {
        return;
    }
    $logDir = $runDir . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logPath = $logDir . DIRECTORY_SEPARATOR . 'run.log';
    $record = json_encode([
        'ts' => gmdate('c'),
        'stage' => 'RUN',
        'level' => 'ERROR',
        'msg' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($record !== false) {
        file_put_contents($logPath, $record . "\n", FILE_APPEND);
    }
}

function compu_run_main(): void
{
    $argv = $_SERVER['argv'] ?? [];
    $options = compu_run_parse_args($argv);

    if (!empty($options['help'])) {
        compu_run_exit(0, compu_run_help_text());
    }

    $stageSpec = $options['stages'] ?? '02..06';
    $stages = compu_run_expand_stages($stageSpec);
    if (empty($stages)) {
    compu_run_exit(2, 'Invalid --stages specification. Allowed values: 01..06 or a comma-separated subset of 01,02,03,04,06.');
    }

    $wpRoot = rtrim($options['wp_root'] ?? getenv('WP_ROOT') ?: __DIR__, DIRECTORY_SEPARATOR);
    if ($wpRoot === '' || !is_dir($wpRoot)) {
        compu_run_exit(2, "Invalid --wp-root path: {$wpRoot}");
    }

    $wpLoad = $wpRoot . DIRECTORY_SEPARATOR . 'wp-load.php';
    if (!is_file($wpLoad)) {
        compu_run_exit(2, "Cannot locate wp-load.php at {$wpLoad}");
    }

    $pluginDirInput = $options['plugin_dir'] ?? getenv('PLUGIN_DIR') ?: ($wpRoot . '/wp-content/plugins/compu-import-lego');
    $pluginDir = compu_run_resolve_plugin_dir($pluginDirInput);
    if ($pluginDir === '' || !is_dir($pluginDir)) {
        compu_run_exit(2, "Plugin directory not found: {$pluginDirInput}");
    }

    $runBase = $options['run_base'] ?? getenv('RUN_BASE') ?: ($wpRoot . '/wp-content/uploads/compu-import');
    $runBase = rtrim($runBase, DIRECTORY_SEPARATOR);

    $phpBin = (string) ($options['php_bin'] ?? PHP_BINARY);
    $wpCli = (string) ($options['wp_cli'] ?? '/usr/local/bin/wp');

    $csvPath = null;
    if (!empty($options['csv'])) {
        $csvCandidate = (string) $options['csv'];
        if (!is_file($csvCandidate)) {
            compu_run_exit(2, "CSV file not found: {$csvCandidate}");
        }
        $resolved = realpath($csvCandidate);
        $csvPath = $resolved ?: $csvCandidate;
    }

    require_once $wpLoad;

    if (!defined('COMPU_IMPORT_UPLOAD_SUBDIR')) {
        define('COMPU_IMPORT_UPLOAD_SUBDIR', 'compu-import');
    }
    if (!defined('COMPU_IMPORT_DEFAULT_CSV')) {
        define('COMPU_IMPORT_DEFAULT_CSV', $csvPath ?? '');
    }

    compu_run_require_plugin_bootstrap($pluginDir);

    if (!class_exists(StageKernel::class)) {
        compu_run_exit(2, 'StageKernel class is not available after loading plugin files.');
    }

    $stage06Path = $pluginDir . '/includes/stages/06-products.php';
    if (!is_file($stage06Path)) {
        compu_run_exit(2, "Required stage script missing: {$stage06Path}");
    }

    $runId = compu_run_normalize_run_id($options['run_id'] ?? null);
    $contextOptions = compu_run_prepare_context($options, $runId, $csvPath);
    $runDirHint = $contextOptions['RUN_DIR'] ?? ($runBase . DIRECTORY_SEPARATOR . 'run-' . $runId);

    $kernel = new StageKernel($wpRoot, $pluginDir, $runBase, $phpBin, $wpCli);

    compu_run_info('stages=' . implode(',', $stages));

    try {
        $result = $kernel->run($stages, $contextOptions);
    } catch (Throwable $e) {
        $message = 'Kernel execution failed: ' . $e->getMessage();
        compu_run_append_runlog($runDirHint, $message);
        compu_run_exit(2, $message);
    }

    $context = is_array($result['context'] ?? null) ? $result['context'] : [];
    $runDir = isset($context['RUN_DIR']) ? (string) $context['RUN_DIR'] : $runDirHint;
    $runIdOut = isset($context['RUN_ID']) ? (string) $context['RUN_ID'] : $runId;

    if ($runIdOut !== '') {
        compu_run_info('run_id=' . $runIdOut);
    }
    if ($runDir !== '') {
        compu_run_info('run_dir=' . $runDir);
    }
    if (!empty($result['summary_path'])) {
        compu_run_info('summary=' . $result['summary_path']);
    }

    $status = strtolower((string) ($result['status'] ?? ''));
    $statusDetail = strtolower((string) ($result['status_detail'] ?? ($result['status_legacy'] ?? '')));

    $finalStatus = $status !== '' ? $status : ($statusDetail === 'error' ? 'error' : 'ok');

    if ($finalStatus === 'error') {
        $detailText = $statusDetail !== '' ? strtoupper($statusDetail) : 'ERROR';
        compu_run_exit(2, 'One or more stages failed (status ' . $detailText . ')');
    }

    $detailText = $statusDetail !== '' ? strtoupper($statusDetail) : 'OK';
    compu_run_exit(0, 'Run completed with status ' . $detailText);
}

try {
    compu_run_main();
} catch (Throwable $e) {
    compu_run_exit(2, 'Fatal error: ' . $e->getMessage());
}
