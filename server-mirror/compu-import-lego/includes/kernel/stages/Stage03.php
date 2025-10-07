<?php
namespace CompuImport\Kernel\Stages;

use CompuImport\Kernel\StageInterface;
use CompuImport\Kernel\StageResult;

class Stage03 implements StageInterface
{
    /** @var \Compu_Stage_Validate|null */
    private $stage;

    public function __construct()
    {
        if (class_exists('\\Compu_Stage_Validate')) {
            $this->stage = new \Compu_Stage_Validate();
        }
    }

    public function id(): string
    {
        return '03';
    }

    public function title(): string
    {
        return 'Validate normalized data';
    }

    public function inputs(): array
    {
        return ['normalized.jsonl'];
    }

    public function outputs(): array
    {
        return ['validated.jsonl'];
    }

    public function run(array $context): StageResult
    {
        $runDir = rtrim((string)($context['RUN_DIR'] ?? ''), DIRECTORY_SEPARATOR);
        if ($runDir === '') {
            return new StageResult(StageResult::STATUS_ERROR, [], [], 'Missing RUN_DIR for stage 03');
        }

        if (!empty($context['DRY_RUN'])) {
            return new StageResult(StageResult::STATUS_WARN, ['dry_run' => 1], [], 'Dry-run: skipped stage execution');
        }

        if ($this->stage === null) {
            return new StageResult(StageResult::STATUS_ERROR, [], [], 'Stage 03 class Compu_Stage_Validate not found');
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
        $normalized = $runDir . DIRECTORY_SEPARATOR . 'normalized.jsonl';
        $validated = $runDir . DIRECTORY_SEPARATOR . 'validated.jsonl';

        $rowsIn = $this->countJsonlRows($normalized);
        $rowsOut = $this->countJsonlRows($validated);
        $skipped = max(0, $rowsIn - $rowsOut);

        $missingSku = 0;
        $missingLvl1 = 0;
        $missingLvl2 = 0;
        if (is_file($validated)) {
            $fh = fopen($validated, 'r');
            if ($fh) {
                while (($line = fgets($fh)) !== false) {
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
                fclose($fh);
            }
        }

        $metrics = [
            'duration_ms' => $duration,
            'rows_in' => $rowsIn,
            'rows_out' => $rowsOut,
            'skipped' => $skipped,
            'missing_sku' => $missingSku,
            'missing_lvl1_id' => $missingLvl1,
            'missing_lvl2_id' => $missingLvl2,
        ];

        $status = ($missingSku === 0 && $missingLvl1 === 0 && $missingLvl2 === 0)
            ? StageResult::STATUS_OK
            : StageResult::STATUS_WARN;

        return new StageResult(
            $status,
            $metrics,
            ['validated_jsonl' => $validated],
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
}
