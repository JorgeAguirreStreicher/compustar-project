<?php
namespace CompuImport\Kernel\Stages;

use CompuImport\Kernel\StageInterface;
use CompuImport\Kernel\StageResult;

class Stage01 implements StageInterface
{
    /** @var string|null */
    private $stageScript;

    /** @var \Compu_Stage_Fetch|null */
    private $stage;

    public function __construct(?string $stageScript = null)
    {
        $this->stageScript = $stageScript;
        $this->initializeStage();
    }

    public function id(): string
    {
        return '01';
    }

    public function title(): string
    {
        return 'Fetch source CSV';
    }

    public function inputs(): array
    {
        return [];
    }

    public function outputs(): array
    {
        return ['source.csv'];
    }

    public function run(array $context): StageResult
    {
        $this->initializeStage();

        $runDir = rtrim((string) ($context['RUN_DIR'] ?? ''), DIRECTORY_SEPARATOR);
        if ($runDir === '') {
            return new StageResult(StageResult::STATUS_ERROR, [], [], 'Missing RUN_DIR for stage 01');
        }

        $sourcePath = (string) ($context['CSV_SRC'] ?? ($context['SOURCE_MASTER'] ?? ''));

        if (!empty($context['DRY_RUN'])) {
            $metrics = ['dry_run' => 1];
            if ($sourcePath !== '' && is_file($sourcePath)) {
                $metrics['rows_available'] = $this->countCsvRows($sourcePath, true);
            }
            return new StageResult(
                StageResult::STATUS_WARN,
                $metrics,
                [],
                'Dry-run: skipped stage execution'
            );
        }

        $destination = $runDir . DIRECTORY_SEPARATOR . 'source.csv';
        $duration = 0;

        try {
            $started = microtime(true);
            if ($this->stage instanceof \Compu_Stage_Fetch) {
                if (file_exists($destination)) {
                    @unlink($destination);
                }
                $args = ['run-dir' => $runDir];
                if ($sourcePath !== '') {
                    $args['file'] = $sourcePath;
                    $args['csv'] = $sourcePath;
                }
                $this->stage->run($args);
            } else {
                if ($sourcePath === '' || !is_file($sourcePath)) {
                    return new StageResult(StageResult::STATUS_ERROR, [], [], 'Stage 01 source CSV not provided');
                }
                if (file_exists($destination)) {
                    @unlink($destination);
                }
                if (!@copy($sourcePath, $destination)) {
                    return new StageResult(StageResult::STATUS_ERROR, [], [], 'Stage 01 failed to copy source CSV');
                }
            }
            $duration = (int) round((microtime(true) - $started) * 1000);
        } catch (\Throwable $e) {
            return new StageResult(
                StageResult::STATUS_ERROR,
                ['exception' => get_class($e)],
                [],
                $e->getMessage()
            );
        }

        if (!is_file($destination)) {
            return new StageResult(
                StageResult::STATUS_WARN,
                ['duration_ms' => $duration],
                [],
                'Stage 01 completed but source.csv not found'
            );
        }

        $metrics = [
            'duration_ms' => $duration,
            'rows_out' => $this->countCsvRows($destination, true),
        ];

        if ($sourcePath !== '' && is_file($sourcePath)) {
            $metrics['rows_in'] = $this->countCsvRows($sourcePath, true);
        }

        return new StageResult(
            StageResult::STATUS_OK,
            $metrics,
            ['source_csv' => $destination],
            null
        );
    }

    private function initializeStage(): void
    {
        if ($this->stage instanceof \Compu_Stage_Fetch) {
            return;
        }

        if (!class_exists('\\Compu_Stage_Fetch') && $this->stageScript && is_file($this->stageScript)) {
            require_once $this->stageScript;
        }

        if (class_exists('\\Compu_Stage_Fetch')) {
            $this->stage = new \Compu_Stage_Fetch();
        }
    }

    private function countCsvRows(string $file, bool $hasHeader): int
    {
        if ($file === '' || !is_file($file) || !is_readable($file)) {
            return 0;
        }

        $count = 0;
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return 0;
        }

        while (fgetcsv($handle, 0, ',', '"', '\\') !== false) {
            $count++;
        }

        fclose($handle);

        if ($hasHeader && $count > 0) {
            $count--;
        }

        return $count;
    }
}
