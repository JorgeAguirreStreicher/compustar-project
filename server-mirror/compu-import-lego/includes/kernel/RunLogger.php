<?php
namespace CompuImport\Kernel;

class RunLogger
{
    /** @var string */
    private $runLog;

    /** @var string */
    private $stageLogDir;

    /**
     * @var resource[]
     */
    private $handles = [];

    public function __construct(string $runLog, string $stageLogDir)
    {
        $this->runLog = $runLog;
        $this->stageLogDir = rtrim($stageLogDir, DIRECTORY_SEPARATOR);
        if (!is_dir($this->stageLogDir)) {
            mkdir($this->stageLogDir, 0775, true);
        }
    }

    public function logRun(string $level, array $payload): void
    {
        $this->writeLine('RUN', $level, $payload, $this->runLog);
    }

    public function logStage(string $stageId, string $level, array $payload = []): void
    {
        $stageFile = $this->stageLogPath($stageId);
        $this->writeLine($stageId, $level, $payload, $stageFile);
        $this->writeLine($stageId, $level, $payload, $this->runLog);
    }

    public function close(): void
    {
        foreach ($this->handles as $handle) {
            fclose($handle);
        }
        $this->handles = [];
    }

    private function stageLogPath(string $stageId): string
    {
        $safe = preg_replace('/[^0-9A-Za-z_-]/', '-', $stageId);
        return $this->stageLogDir . DIRECTORY_SEPARATOR . 'stage-' . $safe . '.log';
    }

    private function writeLine(string $stage, string $level, array $payload, string $file): void
    {
        $record = array_merge([
            'ts' => gmdate('c'),
            'stage' => $stage,
            'level' => strtoupper($level),
        ], $payload);

        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode([
                'ts' => gmdate('c'),
                'stage' => $stage,
                'level' => 'ERROR',
                'msg' => 'Failed to encode log payload',
            ]);
        }

        $handle = $this->handleFor($file);
        if ($handle) {
            fwrite($handle, $json . "\n");
            fflush($handle);
        }
    }

    /**
     * @param string $file
     * @return resource|null
     */
    private function handleFor(string $file)
    {
        if (isset($this->handles[$file])) {
            return $this->handles[$file];
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($file, 'a');
        if ($handle === false) {
            return null;
        }
        $this->handles[$file] = $handle;
        return $handle;
    }
}
