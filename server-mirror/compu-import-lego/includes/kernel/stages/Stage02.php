<?php
namespace CompuImport\Kernel\Stages;

use CompuImport\Kernel\StageInterface;
use CompuImport\Kernel\StageResult;

class Stage02 implements StageInterface
{
    /** @var \Compu_Stage_Normalize|null */
    private $stage;

    public function __construct()
    {
        if (class_exists('\\Compu_Stage_Normalize')) {
            $this->stage = new \Compu_Stage_Normalize();
        }
    }

    public function id(): string
    {
        return '02';
    }

    public function title(): string
    {
        return 'Normalize source CSV';
    }

    public function inputs(): array
    {
        return ['source.csv'];
    }

    public function outputs(): array
    {
        return ['normalized.jsonl', 'normalized.csv'];
    }

    public function run(array $context): StageResult
    {
        $runDir = rtrim((string)($context['RUN_DIR'] ?? ''), DIRECTORY_SEPARATOR);
        if ($runDir === '') {
            return new StageResult(StageResult::STATUS_ERROR, [], [], 'Missing RUN_DIR for stage 02');
        }

        if (!empty($context['DRY_RUN'])) {
            return new StageResult(StageResult::STATUS_WARN, ['dry_run' => 1], [], 'Dry-run: skipped stage execution');
        }

        if ($this->stage === null) {
            return new StageResult(StageResult::STATUS_ERROR, [], [], 'Stage 02 class Compu_Stage_Normalize not found');
        }

        $args = ['run-dir' => $runDir];
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
        $normalizedCsv = $runDir . DIRECTORY_SEPARATOR . 'normalized.csv';
        $sourceCsv = $runDir . DIRECTORY_SEPARATOR . 'source.csv';
        $normalizedJson = $runDir . DIRECTORY_SEPARATOR . 'normalized.jsonl';

        $rowsIn = $this->countCsvRows($sourceCsv, true);
        $rowsOut = $this->countCsvRows($normalizedCsv, true);
        $missingSku = 0;
        $missingLvl1 = 0;
        $missingLvl2 = 0;

        if (is_file($normalizedJson)) {
            $handle = fopen($normalizedJson, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $row = json_decode($line, true);
                    if (!is_array($row)) {
                        continue;
                    }
                    if (empty($row['sku'])) {
                        $missingSku++;
                    }
                    if (empty($row['lvl1_id'])) {
                        $missingLvl1++;
                    }
                    if (empty($row['lvl2_id'])) {
                        $missingLvl2++;
                    }
                }
                fclose($handle);
            }
        }

        $metrics = [
            'duration_ms' => $duration,
            'rows_in' => $rowsIn,
            'rows_out' => $rowsOut,
            'skipped' => max(0, $rowsIn - $rowsOut),
            'missing_sku' => $missingSku,
            'missing_lvl1_id' => $missingLvl1,
            'missing_lvl2_id' => $missingLvl2,
        ];

        return new StageResult(
            ($missingSku === 0 && $missingLvl1 === 0 && $missingLvl2 === 0) ? StageResult::STATUS_OK : StageResult::STATUS_WARN,
            $metrics,
            [
                'normalized_jsonl' => $normalizedJson,
                'normalized_csv' => $normalizedCsv,
            ],
            null
        );
    }

    private function countCsvRows(string $file, bool $hasHeader): int
    {
        if (!is_file($file) || !is_readable($file)) {
            return 0;
        }
        $count = 0;
        if (($handle = fopen($file, 'r')) === false) {
            return 0;
        }
        while (($row = fgetcsv($handle)) !== false) {
            $count++;
        }
        fclose($handle);
        if ($hasHeader && $count > 0) {
            $count--;
        }
        return $count;
    }
}
