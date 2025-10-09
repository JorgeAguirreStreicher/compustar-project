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

        $statusCounts = ['ok' => 0, 'warn' => 0, 'error' => 0];
        $missingMapLvl1 = 0;
        $missingMapLvl2 = 0;
        $missingMapLvl3 = 0;

        if (is_file($metricsPath)) {
            $decodedMetrics = json_decode((string) file_get_contents($metricsPath), true);
            if (is_array($decodedMetrics)) {
                $metrics = array_merge($metrics, array_diff_key($decodedMetrics, array_flip(['status', 'notes'])));
                if (isset($decodedMetrics['status_counts']) && is_array($decodedMetrics['status_counts'])) {
                    foreach ($statusCounts as $key => $value) {
                        if (isset($decodedMetrics['status_counts'][$key])) {
                            $statusCounts[$key] = (int) $decodedMetrics['status_counts'][$key];
                        }
                    }
                }
                $missingMapLvl1 = (int) ($decodedMetrics['missing_map_lvl1'] ?? 0);
                $missingMapLvl2 = (int) ($decodedMetrics['missing_map_lvl2'] ?? 0);
                $missingMapLvl3 = (int) ($decodedMetrics['missing_map_lvl3'] ?? 0);
            }
        } else {
            [$statusCounts, $missingMapLvl1, $missingMapLvl2, $missingMapLvl3] = $this->scanResolvedStatuses($resolved);
        }

        $metrics['status_counts'] = $statusCounts;
        $metrics['missing_map_lvl1'] = $missingMapLvl1;
        $metrics['missing_map_lvl2'] = $missingMapLvl2;
        $metrics['missing_map_lvl3'] = $missingMapLvl3;

        $status = StageResult::STATUS_OK;
        if ($statusCounts['error'] > 0) {
            $status = StageResult::STATUS_WARN;
        } elseif ($statusCounts['warn'] > 0) {
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
     * @return array{0:array{ok:int,warn:int,error:int},1:int,2:int,3:int}
     */
    private function scanResolvedStatuses(string $resolved): array
    {
        $statusCounts = ['ok' => 0, 'warn' => 0, 'error' => 0];
        $missingLvl1 = 0;
        $missingLvl2 = 0;
        $missingLvl3 = 0;

        if (!is_file($resolved) || !is_readable($resolved)) {
            return [$statusCounts, $missingLvl1, $missingLvl2, $missingLvl3];
        }

        $handle = fopen($resolved, 'r');
        if ($handle === false) {
            return [$statusCounts, $missingLvl1, $missingLvl2, $missingLvl3];
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
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }

            if (empty($row['cat_lvl1_id'])) {
                $missingLvl1++;
            }
            if (!empty($row['ID_Menu_Nvl_2']) && empty($row['cat_lvl2_id'])) {
                $missingLvl2++;
            }
            if (!empty($row['ID_Menu_Nvl_3']) && empty($row['cat_lvl3_id'])) {
                $missingLvl3++;
            }
        }

        fclose($handle);

        return [$statusCounts, $missingLvl1, $missingLvl2, $missingLvl3];
    }
}
