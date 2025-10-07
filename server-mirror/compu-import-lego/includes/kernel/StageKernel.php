<?php
namespace CompuImport\Kernel;

use CompuImport\Kernel\Stages\Stage02;
use CompuImport\Kernel\Stages\Stage03;
use CompuImport\Kernel\Stages\Stage04;
use CompuImport\Kernel\Stages\Stage06;

class StageKernel
{
    /** @var string */
    private $wpRoot;

    /** @var string */
    private $pluginDir;

    /** @var string */
    private $runBase;

    /** @var string */
    private $phpBinary;

    /** @var string */
    private $wpCliBinary;

    /** @var array<string,array<string,mixed>> */
    private $stageDefinitions = [];

    public function __construct(string $wpRoot, string $pluginDir, string $runBase, string $phpBinary = 'php', string $wpCliBinary = 'wp')
    {
        $this->wpRoot = rtrim($wpRoot, DIRECTORY_SEPARATOR);
        $this->pluginDir = rtrim($pluginDir, DIRECTORY_SEPARATOR);
        $this->runBase = rtrim($runBase, DIRECTORY_SEPARATOR);
        $this->phpBinary = $phpBinary;
        $this->wpCliBinary = $wpCliBinary;
        $this->buildStageDefinitions();
    }

    /**
     * @param array<int,string> $stageIds
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function run(array $stageIds, array $options = []): array
    {
        [$context, $logger] = $this->initializeRun($options);
        $results = [];
        $overallStatus = StageResult::STATUS_OK;

        $logger->logRun('INFO', [
            'msg' => 'Run started',
            'run_id' => $context['RUN_ID'],
            'stages' => $stageIds,
        ]);

        foreach ($stageIds as $stageId) {
            if (!isset($this->stageDefinitions[$stageId])) {
                $logger->logRun('ERROR', ['msg' => "Stage {$stageId} not defined"]);
                $overallStatus = StageResult::STATUS_ERROR;
                break;
            }

            $definition = $this->stageDefinitions[$stageId];
            $title = $definition['title'];
            $logger->logStage($stageId, 'INFO', [
                'msg' => 'Starting stage',
                'title' => $title,
            ]);

            $this->assertInputs($stageId, $definition, $context, $logger);

            $startTime = microtime(true);
            $result = $this->executeStage($stageId, $definition, $context, $logger);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            if (!isset($result->metrics['duration_ms'])) {
                $result->metrics['duration_ms'] = $durationMs;
            }

            $logger->logStage($stageId, 'METRIC', [
                'metrics' => $result->metrics,
            ]);

            if ($result->notes) {
                $logger->logStage($stageId, 'INFO', ['notes' => $result->notes]);
            }

            $this->assertOutputs($stageId, $definition, $context, $logger);

            $logger->logStage($stageId, 'DONE', [
                'status' => $result->status,
            ]);

            $results[$stageId] = [
                'status' => $result->status,
                'metrics' => $result->metrics,
                'artifacts' => array_filter($result->artifacts, function ($value) {
                    return is_string($value) && $value !== '';
                }),
                'notes' => $result->notes,
            ];

            if ($result->status === StageResult::STATUS_ERROR) {
                $overallStatus = StageResult::STATUS_ERROR;
                break;
            }

            if ($result->status === StageResult::STATUS_WARN && $overallStatus === StageResult::STATUS_OK) {
                $overallStatus = StageResult::STATUS_WARN;
            }
        }

        $summary = [
            'run_id' => $context['RUN_ID'],
            'status' => $overallStatus,
            'stages' => $results,
            'generated_at' => gmdate('c'),
        ];

        $summaryPath = $context['RUN_DIR'] . DIRECTORY_SEPARATOR . 'final' . DIRECTORY_SEPARATOR . 'summary.json';
        file_put_contents($summaryPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $logger->logRun('INFO', [
            'msg' => 'Run finished',
            'status' => $overallStatus,
            'summary' => $summaryPath,
        ]);
        $logger->close();

        $statusDetail = strtolower($overallStatus);
        $statusFlag = $overallStatus === StageResult::STATUS_ERROR ? 'error' : 'ok';

        return [
            'status' => $statusFlag,
            'status_detail' => $statusDetail,
            'status_legacy' => $overallStatus,
            'summary' => $summary,
            'summary_path' => $summaryPath,
            'context' => $context,
        ];
    }

    /**
     * @return array{0:array<string,mixed>,1:RunLogger}
     */
    private function initializeRun(array $options): array
    {
        $defaults = [
            'CSV_SRC' => getenv('CSV_SRC') ?: ($options['CSV_SRC'] ?? ''),
            'SOURCE_MASTER' => getenv('SOURCE_MASTER') ?: ($options['SOURCE_MASTER'] ?? ''),
            'DRY_RUN' => (int) ($options['DRY_RUN'] ?? getenv('DRY_RUN') ?: 0),
            'LIMIT' => (int) ($options['LIMIT'] ?? getenv('LIMIT') ?: 0),
            'OFFSET' => (int) ($options['OFFSET'] ?? getenv('OFFSET') ?: 0),
            'REQUIRE_TERM' => (int) ($options['REQUIRE_TERM'] ?? getenv('REQUIRE_TERM') ?: 0),
            'SAMPLE600' => (int) ($options['SAMPLE600'] ?? getenv('SAMPLE600') ?: 0),
            'SUBSET_FROM' => (int) ($options['SUBSET_FROM'] ?? getenv('SUBSET_FROM') ?: 0),
            'SUBSET_ROWS' => (int) ($options['SUBSET_ROWS'] ?? getenv('SUBSET_ROWS') ?: 0),
        ];

        $runId = $options['RUN_ID'] ?? $this->generateRunId();
        $runDir = $options['RUN_DIR'] ?? ($this->runBase . DIRECTORY_SEPARATOR . 'run-' . $runId);

        $this->ensureDirectory($runDir);
        $this->ensureDirectory($runDir . DIRECTORY_SEPARATOR . 'logs');
        $this->ensureDirectory($runDir . DIRECTORY_SEPARATOR . 'final');
        $this->ensureDirectory($runDir . DIRECTORY_SEPARATOR . 'tmp');

        if ($defaults['CSV_SRC'] === '' && defined('COMPU_IMPORT_DEFAULT_CSV')) {
            $defaults['CSV_SRC'] = COMPU_IMPORT_DEFAULT_CSV;
        }

        if ($defaults['SOURCE_MASTER'] === '' && defined('COMPU_IMPORT_DEFAULT_CSV')) {
            $defaults['SOURCE_MASTER'] = COMPU_IMPORT_DEFAULT_CSV;
        }

        $context = array_merge($defaults, [
            'RUN_ID' => $runId,
            'RUN_DIR' => $runDir,
            'RUN_PATH' => $runDir,
            'RUN_BASE' => $this->runBase,
        ]);

        $this->exportContextEnv($context);
        $this->prepareSourceCsv($context);

        $runLog = $runDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'run.log';
        $stageLogDir = $runDir . DIRECTORY_SEPARATOR . 'logs';
        $logger = new RunLogger($runLog, $stageLogDir);

        return [$context, $logger];
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $context
     */
    private function executeStage(string $stageId, array $definition, array $context, RunLogger $logger): StageResult
    {
        $this->ensureWpCliStub();

        if ($definition['type'] === 'php') {
            /** @var StageInterface $handler */
            $handler = $definition['handler'];
            return $handler->run($context);
        }

        if ($definition['type'] === 'wp-cli') {
            if (!empty($context['DRY_RUN'])) {
                return new StageResult(
                    StageResult::STATUS_WARN,
                    ['dry_run' => 1],
                    [],
                    'Dry-run: stage skipped'
                );
            }
            $command = $this->buildWpCliCommand($definition['eval']);
            $env = $this->collectEnvForStage($context, $stageId);
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open($command, $descriptorSpec, $pipes, $this->wpRoot, $env);
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

            $metrics = ['rows_in' => 0, 'rows_out' => 0, 'skipped' => 0];
            $parsedMetrics = $this->parseJsonMetrics($stdout);
            if ($parsedMetrics) {
                $metrics = array_merge($metrics, $parsedMetrics);
            }

            $logger->logStage($stageId, 'INFO', [
                'stdout' => trim($stdout),
                'stderr' => trim($stderr),
            ]);

            return new StageResult(
                $exitCode === 0 ? StageResult::STATUS_OK : StageResult::STATUS_ERROR,
                $metrics,
                [
                    'stdout' => $this->writeStageOutput($context, $stageId, 'stdout.log', $stdout),
                    'stderr' => $this->writeStageOutput($context, $stageId, 'stderr.log', $stderr),
                ],
                $exitCode === 0 ? null : 'Stage exited with code ' . $exitCode
            );
        }

        return new StageResult(StageResult::STATUS_ERROR, [], [], 'Unknown stage type');
    }

    private function buildStageDefinitions(): void
    {
        $this->stageDefinitions = [];
        $stage02 = new Stage02();
        $stage03 = new Stage03();
        $stage04 = new Stage04();
        $stage06 = new Stage06($this->phpBinary, $this->pluginDir . '/includes/stages/06-products.php');

        foreach ([$stage02, $stage03, $stage04, $stage06] as $handler) {
            $this->stageDefinitions[$handler->id()] = [
                'type' => 'php',
                'title' => $handler->title(),
                'handler' => $handler,
                'inputs' => $handler->inputs(),
                'outputs' => $handler->outputs(),
            ];
        }

        $this->stageDefinitions['05'] = [
            'type' => 'wp-cli',
            'title' => 'Term resolver',
            'eval' => 'define("COMP_RUN_STAGE", true); include_once "' . addslashes($this->pluginDir . '/includes/stages/05-terms.php') . '";',
            'inputs' => ['resolved.jsonl', 'validated.jsonl'],
            'outputs' => ['terms_resolved.jsonl'],
        ];

        $wpStages = [
            '07' => ['file' => '/includes/stages/07-media.php', 'title' => 'Media sync'],
            '08' => ['file' => '/includes/stages/08-offers.php', 'title' => 'Offers sync'],
            '09' => ['file' => '/includes/stages/09-pricing.php', 'title' => 'Pricing adjustments'],
            '10' => ['file' => '/includes/stages/10-publish.php', 'title' => 'Publish products'],
            '11' => ['file' => '/includes/stages/11-report.php', 'title' => 'Generate report'],
        ];

        foreach ($wpStages as $id => $stage) {
            $this->stageDefinitions[$id] = [
                'type' => 'wp-cli',
                'title' => $stage['title'],
                'eval' => 'define("COMP_RUN_STAGE", true); include_once "' . addslashes($this->pluginDir . $stage['file']) . '";',
                'inputs' => [],
                'outputs' => [],
            ];
        }
    }

    /**
     * @return array<string,string>
     */
    private function collectEnvForStage(array $context, string $stageId): array
    {
        $env = $_ENV;
        $keys = ['RUN_ID', 'RUN_DIR', 'RUN_PATH', 'RUN_BASE', 'CSV_SRC', 'SOURCE_MASTER', 'DRY_RUN', 'LIMIT', 'OFFSET', 'REQUIRE_TERM', 'SAMPLE600', 'SUBSET_FROM', 'SUBSET_ROWS'];
        foreach ($keys as $key) {
            if (isset($context[$key])) {
                $env[$key] = (string) $context[$key];
            }
        }
        $env['COMP_RUN_STAGE_ID'] = $stageId;
        return $env;
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 02775, true);
        }
        @chmod($dir, 02775);
        @chown($dir, 'compustar');
        @chgrp($dir, 'compustar');
    }

    private function generateRunId(): string
    {
        try {
            $random = substr(bin2hex(random_bytes(3)), 0, 6);
        } catch (\Throwable $e) {
            $random = (string) mt_rand(100000, 999999);
        }
        return date('Ymd-His') . '-' . $random;
    }

    private function prepareSourceCsv(array $context): void
    {
        $csvSrc = (string) ($context['CSV_SRC'] ?? '');
        if ($csvSrc === '' || !is_file($csvSrc)) {
            return;
        }
        $destination = $context['RUN_DIR'] . DIRECTORY_SEPARATOR . 'source.csv';
        if (file_exists($destination)) {
            return;
        }
        if (!@symlink($csvSrc, $destination)) {
            @copy($csvSrc, $destination);
        }
        @chmod($destination, 0664);
        @chown($destination, 'compustar');
        @chgrp($destination, 'compustar');
    }

    private function exportContextEnv(array $context): void
    {
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $stringValue = $value === null ? '' : (string) $value;
                putenv($key . '=' . $stringValue);
                $_ENV[$key] = $stringValue;
            }
        }
    }

    private function ensureWpCliStub(): void
    {
        if (!class_exists('\\WP_CLI')) {
            class_alias(StubWpCli::class, '\\WP_CLI');
        }
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $context
     */
    private function assertInputs(string $stageId, array $definition, array $context, RunLogger $logger): void
    {
        $inputs = $definition['inputs'] ?? [];
        foreach ($inputs as $input) {
            $path = $context['RUN_DIR'] . DIRECTORY_SEPARATOR . $input;
            if (!file_exists($path)) {
                $logger->logStage($stageId, 'WARN', ['msg' => 'Missing input artifact', 'artifact' => $input]);
            }
        }
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $context
     */
    private function assertOutputs(string $stageId, array $definition, array $context, RunLogger $logger): void
    {
        $outputs = $definition['outputs'] ?? [];
        foreach ($outputs as $output) {
            $path = $context['RUN_DIR'] . DIRECTORY_SEPARATOR . $output;
            if (!file_exists($path)) {
                $logger->logStage($stageId, 'WARN', ['msg' => 'Expected output artifact missing', 'artifact' => $output]);
            }
        }
    }

    private function parseJsonMetrics(string $stdout): array
    {
        $lines = preg_split('/\r?\n/', trim($stdout));
        $lines = $lines ?: [];
        foreach ($lines as $line) {
            $decoded = json_decode(trim($line), true);
            if (is_array($decoded) && isset($decoded['stage'])) {
                $metrics = $decoded;
                unset($metrics['stage']);
                return $metrics;
            }
        }
        return [];
    }

    private function writeStageOutput(array $context, string $stageId, string $fileName, string $contents): string
    {
        if (trim($contents) === '') {
            return '';
        }
        $logsDir = $context['RUN_DIR'] . DIRECTORY_SEPARATOR . 'logs';
        $path = $logsDir . DIRECTORY_SEPARATOR . 'stage-' . $stageId . '-' . $fileName;
        file_put_contents($path, $contents);
        return $path;
    }

    private function buildWpCliCommand(string $evalCode): string
    {
        $cmd = escapeshellcmd($this->wpCliBinary);
        $cmd .= ' --path=' . escapeshellarg($this->wpRoot);
        $cmd .= ' eval ' . escapeshellarg($evalCode);
        return $cmd;
    }
}

class StubWpCli
{
    public static function log($message): void
    {
        fwrite(STDOUT, '[WP_CLI] ' . $message . "\n");
    }

    public static function error($message): void
    {
        throw new \RuntimeException(is_string($message) ? $message : json_encode($message));
    }

    public static function success($message): void
    {
        self::log($message);
    }
}
