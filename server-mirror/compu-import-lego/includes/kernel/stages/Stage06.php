<?php
namespace CompuImport\Kernel\Stages;

use CompuImport\Kernel\StageInterface;
use CompuImport\Kernel\StageResult;

class Stage06 implements StageInterface
{
    /** @var string */
    private $phpBinary;

    /** @var string */
    private $stagePath;

    public function __construct(string $phpBinary, string $stagePath)
    {
        $this->phpBinary = $phpBinary;
        $this->stagePath = $stagePath;
    }

    public function id(): string
    {
        return '06';
    }

    public function title(): string
    {
        return 'Products simulation writer';
    }

    public function inputs(): array
    {
        return ['resolved.jsonl', 'validated.jsonl'];
    }

    public function outputs(): array
    {
        return ['final/imported.csv', 'final/updated.csv', 'final/skipped.csv'];
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

        if (!is_file($this->stagePath)) {
            return new StageResult(StageResult::STATUS_ERROR, [], [], 'Stage 06 script not found');
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = array_merge($_ENV, [
            'RUN_ID' => (string)($context['RUN_ID'] ?? ''),
            'RUN_DIR' => $runDir,
            'LIMIT' => (string)($context['LIMIT'] ?? ''),
            'OFFSET' => (string)($context['OFFSET'] ?? ''),
            'DRY_RUN' => (string)($context['DRY_RUN'] ?? ''),
        ]);

        $command = escapeshellcmd($this->phpBinary) . ' ' . escapeshellarg($this->stagePath);
        $process = proc_open($command, $descriptorSpec, $pipes, $runDir, $env);
        $stdout = '';
        $stderr = '';
        $exitCode = 1;

        if (is_resource($process)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
        }

        $metrics = [
            'rows_in' => $this->countInputRows($runDir),
            'rows_out' => $this->countCsvRows($runDir . '/final/imported.csv', true)
                + $this->countCsvRows($runDir . '/final/updated.csv', true),
            'skipped' => $this->countCsvRows($runDir . '/final/skipped.csv', true),
        ];

        $status = $exitCode === 0 ? StageResult::STATUS_OK : StageResult::STATUS_ERROR;
        $notes = trim($stderr) !== '' ? trim($stderr) : null;

        return new StageResult(
            $status,
            $metrics,
            [
                'imported_csv' => $runDir . '/final/imported.csv',
                'updated_csv' => $runDir . '/final/updated.csv',
                'skipped_csv' => $runDir . '/final/skipped.csv',
                'stdout' => $stdout !== '' ? $this->writeArtifact($runDir, 'stage06.stdout.log', $stdout) : '',
                'stderr' => $stderr !== '' ? $this->writeArtifact($runDir, 'stage06.stderr.log', $stderr) : '',
            ],
            $notes
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
        while (($row = fgetcsv($fh)) !== false) {
            $count++;
        }
        fclose($fh);
        if ($hasHeader && $count > 0) {
            $count--;
        }
        return $count;
    }

    private function writeArtifact(string $runDir, string $fileName, string $contents): string
    {
        $logsDir = $runDir . '/logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0775, true);
        }
        $path = $logsDir . '/' . $fileName;
        file_put_contents($path, $contents);
        return $path;
    }
}
