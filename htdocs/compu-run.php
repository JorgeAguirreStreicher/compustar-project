#!/usr/bin/env php
<?php
declare(strict_types=1);

use CompuImport\Kernel\StageKernel;

function compu_run_parse_args(array $argv): array {
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

function compu_run_print_help(): void {
    $help = <<<TEXT
Compu Import unified runner
Usage:
  php compu-run.php --stages=02..06 [options]

Options:
  --stages=LIST          Stage list (e.g. 02..06 or 02,03,04)
  --dry-run=0|1          Skip execution and only prepare run context
  --require-term=0|1     Require taxonomy term mapping (propagated to stages)
  --limit=N              Limit records processed by applicable stages
  --offset=N             Skip first N records (stage 06)
  --from=N               Start subset at row N (alias of --subset-from)
  --rows=N               Number of rows for subset (alias of --subset-rows)
  --csv=PATH             Source CSV file to link as source.csv
  --run-base=PATH        Override RUN_BASE directory
  --run-dir=PATH         Reuse an existing run directory
  --run-id=VALUE         Provide a run identifier (otherwise generated)
  --wp-root=PATH         Override WordPress root
  --plugin-dir=PATH      Override plugin directory
  --wp-cli=PATH          Path to wp binary (default /usr/local/bin/wp)
  --php-bin=PATH         PHP binary for sub-process stages (default PHP_BINARY)
  --help                 Show this message
TEXT;
    fwrite(STDOUT, $help . "\n");
}

function compu_run_expand_stages(string $spec): array {
    $spec = trim($spec);
    if ($spec === '') {
        return [];
    }

    $parts = [];

    if (strpos($spec, '..') !== false) {
        [$start, $end] = explode('..', $spec, 2);
        if ($start === '' || $end === '') {
            return [];
        }
        $startInt = (int) $start;
        $endInt = (int) $end;
        if ($startInt <= 0 || $endInt <= 0 || $startInt > $endInt) {
            return [];
        }
        for ($i = $startInt; $i <= $endInt; $i++) {
            $parts[] = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        }
        return $parts;
    }

    foreach (explode(',', $spec) as $item) {
        $item = trim($item);
        if ($item === '') {
            continue;
        }
        if (!preg_match('/^\d{2}$/', $item)) {
            return [];
        }
        $parts[] = $item;
    }

    return array_values(array_unique($parts));
}

function compu_run_exit(int $code, string $message = ''): void {
    if ($message !== '') {
        if ($code === 0) {
            fwrite(STDOUT, $message . "\n");
        } else {
            fwrite(STDERR, $message . "\n");
        }
    }
    exit($code);
}

$argv = $_SERVER['argv'] ?? [];
$options = compu_run_parse_args($argv);

if (!empty($options['help'])) {
    compu_run_print_help();
    exit(0);
}

$stageSpec = $options['stages'] ?? '02..06';
$stages = compu_run_expand_stages($stageSpec);
if (empty($stages)) {
    compu_run_exit(2, 'Invalid --stages specification');
}

$wpRoot = $options['wp_root'] ?? getenv('WP_ROOT') ?: __DIR__;
$wpLoad = rtrim($wpRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wp-load.php';
if (!is_file($wpLoad)) {
    compu_run_exit(2, "Cannot locate wp-load.php at {$wpLoad}");
}

require_once $wpLoad;

$pluginDir = $options['plugin_dir'] ?? getenv('PLUGIN_DIR') ?: ($wpRoot . '/wp-content/plugins/compu-import-lego');
if (!is_dir($pluginDir)) {
    compu_run_exit(2, "Plugin directory not found: {$pluginDir}");
}

$runBase = $options['run_base'] ?? getenv('RUN_BASE') ?: ($wpRoot . '/wp-content/uploads/compu-import');
$phpBin = $options['php_bin'] ?? PHP_BINARY;
$wpCli = $options['wp_cli'] ?? '/usr/local/bin/wp';

require_once $pluginDir . '/includes/kernel/StageInterface.php';
require_once $pluginDir . '/includes/kernel/StageResult.php';
require_once $pluginDir . '/includes/kernel/RunLogger.php';
require_once $pluginDir . '/includes/kernel/stages/Stage02.php';
require_once $pluginDir . '/includes/kernel/stages/Stage03.php';
require_once $pluginDir . '/includes/kernel/stages/Stage04.php';
require_once $pluginDir . '/includes/kernel/stages/Stage06.php';
require_once $pluginDir . '/includes/kernel/StageKernel.php';

$kernel = new StageKernel($wpRoot, $pluginDir, $runBase, $phpBin, $wpCli);

$contextOptions = [];
$map = [
    'dry_run' => 'DRY_RUN',
    'require_term' => 'REQUIRE_TERM',
    'limit' => 'LIMIT',
    'offset' => 'OFFSET',
    'from' => 'SUBSET_FROM',
    'rows' => 'SUBSET_ROWS',
    'subset_from' => 'SUBSET_FROM',
    'subset_rows' => 'SUBSET_ROWS',
    'csv' => 'CSV_SRC',
    'source' => 'CSV_SRC',
    'source_master' => 'SOURCE_MASTER',
    'sample600' => 'SAMPLE600',
    'run_dir' => 'RUN_DIR',
    'run_id' => 'RUN_ID',
];

foreach ($map as $optionKey => $contextKey) {
    if (isset($options[$optionKey])) {
        $value = $options[$optionKey];
        if (in_array($contextKey, ['DRY_RUN', 'REQUIRE_TERM', 'LIMIT', 'OFFSET', 'SUBSET_FROM', 'SUBSET_ROWS', 'SAMPLE600'], true)) {
            $value = (int) $value;
        }
        $contextOptions[$contextKey] = $value;
    }
}

$contextOptions['CSV_SRC'] = $contextOptions['CSV_SRC'] ?? ($options['csv_src'] ?? null);
if ($contextOptions['CSV_SRC'] === null) {
    unset($contextOptions['CSV_SRC']);
}

fwrite(STDOUT, '[compu-run] stages=' . implode(',', $stages) . "\n");

try {
    $result = $kernel->run($stages, $contextOptions);
} catch (Throwable $e) {
    compu_run_exit(3, 'Kernel execution failed: ' . $e->getMessage());
}

$summary = $result['summary'] ?? [];
$runId = $summary['run_id'] ?? ($result['context']['RUN_ID'] ?? 'unknown');
$runDir = $result['context']['RUN_DIR'] ?? '';
$summaryPath = $result['summary_path'] ?? '';
$status = $result['status'] ?? 'ERROR';

fwrite(STDOUT, '[compu-run] run_id=' . $runId . "\n");
if ($runDir !== '') {
    fwrite(STDOUT, '[compu-run] run_dir=' . $runDir . "\n");
}
if ($summaryPath !== '') {
    fwrite(STDOUT, '[compu-run] summary=' . $summaryPath . "\n");
}

if ($status === 'ERROR') {
    compu_run_exit(3, 'One or more stages failed');
}

compu_run_exit(0, 'Run completed with status ' . $status);
