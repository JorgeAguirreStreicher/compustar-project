<?php
namespace CompuImport\Kernel\Stages;

use CompuImport\Kernel\StageInterface;
use CompuImport\Kernel\StageResult;

class Stage04 implements StageInterface
{
    /** @var \Compu_Stage_Resolve_Map|null */
    private $stage;

    public function __construct()
    {
        if (!class_exists('\\Compu_Stage_Resolve_Map')) {
            $stagePath = dirname(__DIR__, 2) . '/stages/04-resolve-map.php';
            if (is_file($stagePath)) {
                require_once $stagePath;
            }
        }

        if (class_exists('\\Compu_Stage_Resolve_Map')) {
            $this->stage = new \Compu_Stage_Resolve_Map();
        }
    }

    public function id(): string
    {
        return '04';
    }

    public function title(): string
    {
        return 'Resolve category mapping';
    }

    public function inputs(): array
    {
        return ['validated.jsonl'];
    }

    public function outputs(): array
    {
        return ['resolved.jsonl', 'logs/stage-04.log'];
    }

    public function run(array $context): StageResult
    {
        $runDir = rtrim((string)($context['RUN_DIR'] ?? ''), DIRECTORY_SEPARATOR);
        if ($runDir === '') {
            return new StageResult(StageResult::STATUS_ERROR, [], [], 'Missing RUN_DIR for stage 04');
        }

        if (!empty($context['DRY_RUN'])) {
            return new StageResult(StageResult::STATUS_WARN, ['dry_run' => 1], [], 'Dry-run: skipped stage execution');
        }

        if ($this->stage === null) {
            return new StageResult(StageResult::STATUS_ERROR, [], [], 'Stage 04 class Compu_Stage_Resolve_Map not found');
        }

        $args = [
            'run-id' => $context['RUN_DB_ID'] ?? $context['RUN_ID'] ?? null,
        ];

        $started = microtime(true);
        try {
            $this->stage->run($args);
        } catch (\Throwable $e) {
            return new StageResult(
                StageResult::STATUS_ERROR,
                ['exception' => get_class($e)],
                [],
                $e->getMessage()
            );
        }

        $duration = (int) round((microtime(true) - $started) * 1000);
        $validated = $runDir . DIRECTORY_SEPARATOR . 'validated.jsonl';
        $resolved = $runDir . DIRECTORY_SEPARATOR . 'resolved.jsonl';
        $logPath = $runDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'stage-04.log';
        $metricsPath = $runDir . DIRECTORY_SEPARATOR . 'stage-04.metrics.json';

        $rowsIn = $this->countJsonlRows($validated);
        $rowsOut = $this->countJsonlRows($resolved);

        $metrics = [
            'duration_ms' => $duration,
            'rows_in' => $rowsIn,
            'rows_out' => $rowsOut,
        ];

        $statusCounts = [];
        $blockedLvl1 = 0;

        if (is_file($metricsPath)) {
            $decodedMetrics = json_decode((string) file_get_contents($metricsPath), true);
            if (is_array($decodedMetrics)) {
                $metrics = array_merge($metrics, array_diff_key($decodedMetrics, array_flip(['status', 'notes'])));
                if (isset($decodedMetrics['status_counts']) && is_array($decodedMetrics['status_counts'])) {
                    foreach ($decodedMetrics['status_counts'] as $statusKey => $statusValue) {
                        $statusCounts[$statusKey] = (int) $statusValue;
                    }
                }
                $blockedLvl1 = (int) ($decodedMetrics['blocked_lvl1'] ?? 0);
            }
        } else {
            [$statusCounts, $blockedLvl1] = $this->scanResolvedStatuses($resolved);
        }

        $metrics['status_counts'] = $statusCounts;
        $metrics['blocked_lvl1'] = $blockedLvl1;

        $status = StageResult::STATUS_OK;
        $errorCount = (int) ($statusCounts['error'] ?? 0);
        $warnCount = (int) ($statusCounts['warn'] ?? 0);
        if ($errorCount > 0 || $warnCount > 0) {
            $status = StageResult::STATUS_WARN;
        }

        return new StageResult(
            $status,
            $metrics,
            [
                'resolved_jsonl' => $resolved,
                'log' => $logPath,
                'metrics_json' => is_file($metricsPath) ? $metricsPath : '',
            ],
            null
        );
    }

    private function countJsonlRows(string $file): int
    {
        if (!is_file($file) || !is_readable($file)) {
            return 0;
        }
        $count = 0;
        $fh = fopen($file, 'r');
        if (!$fh) {
            return 0;
        }
        while (($line = fgets($fh)) !== false) {
            if (trim($line) !== '') {
                $count++;
            }
        }
        fclose($fh);
        return $count;
    }

    private function countCsvRows(string $file, bool $hasHeader): int
    {
        if (!is_file($file) || !is_readable($file)) {
            return 0;
        }
        $fh = fopen($file, 'r');
        if (!$fh) {
            return 0;
        }
        $count = 0;
        while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            $count++;
        }
        fclose($fh);
        if ($hasHeader && $count > 0) {
            $count--;
        }
        return $count;
    }

    /**
     * @return array{0:array<string,int>,1:int}
     */
    private function scanResolvedStatuses(string $resolved): array
    {
        $statusCounts = [];
        $blockedLvl1 = 0;

        if (!is_file($resolved) || !is_readable($resolved)) {
            return [$statusCounts, $blockedLvl1];
        }

        $handle = fopen($resolved, 'r');
        if ($handle === false) {
            return [$statusCounts, $blockedLvl1];
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (!is_array($row)) {
                continue;
            }
            $status = strtolower((string) ($row['resolve_status'] ?? ''));
            if ($status === '') {
                $status = 'ok';
            }
            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
            $statusCounts[$status]++;

            if ($status === 'blocked_lvl1') {
                $blockedLvl1++;
            }
        }

        fclose($handle);

        return [$statusCounts, $blockedLvl1];
    }
}
