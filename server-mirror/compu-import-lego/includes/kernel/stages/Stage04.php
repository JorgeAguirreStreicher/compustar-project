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
        return ['resolved.jsonl', 'unmapped.csv'];
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
        $unmapped = $runDir . DIRECTORY_SEPARATOR . 'unmapped.csv';

        $rowsIn = $this->countJsonlRows($validated);
        $rowsOut = $this->countJsonlRows($resolved);
        $skipped = max(0, $rowsIn - $rowsOut);

        $unmappedRows = $this->countCsvRows($unmapped, true);

        $metrics = [
            'duration_ms' => $duration,
            'rows_in' => $rowsIn,
            'rows_out' => $rowsOut,
            'skipped' => $skipped,
            'unmapped' => $unmappedRows,
        ];

        $status = $unmappedRows > 0 ? StageResult::STATUS_WARN : StageResult::STATUS_OK;

        return new StageResult(
            $status,
            $metrics,
            [
                'resolved_jsonl' => $resolved,
                'unmapped_csv' => $unmapped,
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
}
