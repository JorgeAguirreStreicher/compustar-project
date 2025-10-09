<?php
namespace CompuImport\Kernel\Stages;

use CompuImport\Kernel\StageInterface;
use CompuImport\Kernel\StageResult;

class Stage06 implements StageInterface
{
    /** @var \Compu_Stage_Finalize|null */
    private $stage;

    public function __construct()
    {
        if (!class_exists('\\Compu_Stage_Finalize')) {
            $stagePath = dirname(__DIR__, 2) . '/stages/06-products.php';
            if (is_file($stagePath)) {
                require_once $stagePath;
            }
        }

        if (class_exists('\\Compu_Stage_Finalize')) {
            $this->stage = new \Compu_Stage_Finalize();
        }
    }

    public function id(): string
    {
        return '06';
    }

    public function title(): string
    {
        return 'Final packaging builder';
    }

    public function inputs(): array
    {
        return ['resolved.jsonl', 'validated.jsonl'];
    }

    public function outputs(): array
    {
        return ['final/import-ready.csv', 'final/skipped.csv', 'final/summary.json'];
    }

    public function run(array $context): StageResult
    {
        $runDir = rtrim((string)($context['RUN_DIR'] ?? ''), DIRECTORY_SEPARATOR);
        if ($runDir === '') {
            return new StageResult(StageResult::STATUS_ERROR, [], [], 'Missing RUN_DIR for stage 06');
        }

        if (!empty($context['DRY_RUN'])) {
            return new StageResult(StageResult::STATUS_WARN, ['dry_run' => 1], [], 'Dry-run: skipped stage execution');
        }

        if ($this->stage === null) {
            return new StageResult(StageResult::STATUS_ERROR, [], [], 'Stage 06 class Compu_Stage_Finalize not found');
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
        $resolvedPath = $runDir . '/resolved.jsonl';
        $importCsv = $runDir . '/final/import-ready.csv';
        $skippedCsv = $runDir . '/final/skipped.csv';
        $summaryJson = $runDir . '/final/summary.json';
        $logPath = $runDir . '/logs/stage-06.log';

        $rowsIn = $this->countInputRows($runDir);
        $importRows = $this->countCsvRows($importCsv, true);
        $skippedRows = $this->countCsvRows($skippedCsv, true);

        $metrics = [
            'duration_ms' => $duration,
            'rows_in' => $rowsIn,
            'import_ready' => $importRows,
            'skipped' => $skippedRows,
        ];

        if (is_file($summaryJson)) {
            $summary = json_decode((string) file_get_contents($summaryJson), true);
            if (is_array($summary)) {
                $metrics = array_merge($metrics, array_diff_key($summary, array_flip(['skipped_reasons', 'generated_at'])));
                if (isset($summary['skipped_reasons']) && is_array($summary['skipped_reasons'])) {
                    $metrics['skipped_reasons'] = $summary['skipped_reasons'];
                }
            }
        }

        $status = StageResult::STATUS_OK;
        if ($skippedRows > 0) {
            $skippedReasons = $metrics['skipped_reasons'] ?? [];
            $blockedCount = 0;
            if (is_array($skippedReasons) && isset($skippedReasons['blocked_lvl1'])) {
                $blockedCount = (int) $skippedReasons['blocked_lvl1'];
            }
            if ($blockedCount !== $skippedRows) {
                $status = StageResult::STATUS_WARN;
            }
        }

        return new StageResult(
            $status,
            $metrics,
            [
                'resolved_jsonl' => $resolvedPath,
                'import_ready_csv' => $importCsv,
                'skipped_csv' => $skippedCsv,
                'summary_json' => $summaryJson,
                'log' => $logPath,
            ],
            null
        );
    }

    private function countInputRows(string $runDir): int
    {
        $resolved = $runDir . '/resolved.jsonl';
        $validated = $runDir . '/validated.jsonl';
        $rowsResolved = $this->countJsonlRows($resolved);
        if ($rowsResolved > 0) {
            return $rowsResolved;
        }
        return $this->countJsonlRows($validated);
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
}
