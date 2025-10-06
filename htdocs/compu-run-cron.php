<?php
declare(strict_types=1);

$baseRunDir = __DIR__ . '/wp-content/uploads/compu-import';
if (!is_dir($baseRunDir) && !@mkdir($baseRunDir, 0775, true) && !is_dir($baseRunDir)) {
    fwrite(STDERR, "[cron] FAILED_TO_CREATE_BASE_DIR $baseRunDir\n");
    exit(10);
}

$runId = (string) (time());
$runDir = $baseRunDir . '/run-' . $runId;
if (!is_dir($runDir) && !@mkdir($runDir, 0775, true) && !is_dir($runDir)) {
    fwrite(STDERR, "[cron] FAILED_TO_CREATE_RUN_DIR $runDir\n");
    exit(11);
}

$token = getenv('COMPU_RUN_TOKEN');
if ($token === false || $token === '') {
    $token = 'dev-compustar-token';
    putenv('COMPU_RUN_TOKEN=' . $token);
}

echo "RUN_DIR=$runDir\n";

$_GET = [
    'token'   => $token,
    'stage'   => '01',
    'run_id'  => $runId,
    'run_dir' => $runDir,
];

require __DIR__ . '/compu-run-normalize.php';
