<?php
if (!defined('ABSPATH')) {
  exit;
}

class Compu_Stage_Fetch {
  /**
   * @param array<string,mixed> $args
   */
  public function run($args) {
    $sourceFile = $this->resolveSourceFile($args);
    if (!file_exists($sourceFile)) {
      $this->cli_error("No encuentro el archivo: {$sourceFile}");
    }

    $runSetup = $this->resolveRunDirectory($args, $sourceFile);
    $runDir   = $runSetup['dir'];
    $runId    = $runSetup['id'];

    if (!is_dir($runDir) && !mkdir($runDir, 0777, true) && !is_dir($runDir)) {
      $this->cli_error("No se pudo crear el directorio de ejecución: {$runDir}");
    }

    $destination = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'source.csv';

    if (!copy($sourceFile, $destination)) {
      $this->cli_error('No se pudo copiar el archivo fuente al directorio de ejecución.');
    }

    $this->cli_log("Fuente copiada a {$destination}");
    if ($runId !== null) {
      $this->compu_log($runId, 'fetch', 'info', 'Archivo copiado', [
        'src'  => $sourceFile,
        'dest' => $destination,
      ]);
      $this->cli_success("Run {$runId} inicializado. Fuente copiada a {$destination}");
    }
  }

  /**
   * @param array<string,mixed> $args
   */
  private function resolveSourceFile(array $args): string {
    $candidates = [];
    foreach (['file', 'csv', 'source'] as $key) {
      if (!empty($args[$key])) {
        $candidates[] = (string) $args[$key];
      }
    }
    $envCsv = getenv('CSV');
    if ($envCsv !== false && $envCsv !== '') {
      $candidates[] = (string) $envCsv;
    }
    if (defined('COMPU_IMPORT_DEFAULT_CSV')) {
      $candidates[] = (string) COMPU_IMPORT_DEFAULT_CSV;
    }
    foreach ($candidates as $candidate) {
      $candidate = trim($candidate);
      if ($candidate !== '') {
        return $candidate;
      }
    }
    $this->cli_error('No se indicó un archivo CSV fuente.');
    return '';
  }

  /**
   * @param array<string,mixed> $args
   * @return array{id: int|null, dir: string}
   */
  private function resolveRunDirectory(array $args, string $sourceFile): array {
    $runDirCandidates = [];
    foreach (['run-dir', 'run_dir', 'runDir', 'dir', 'path'] as $key) {
      if (!empty($args[$key])) {
        $runDirCandidates[] = (string) $args[$key];
      }
    }
    foreach (['RUN_DIR', 'RUN_PATH'] as $envKey) {
      $envValue = getenv($envKey);
      if ($envValue !== false && $envValue !== '') {
        $runDirCandidates[] = (string) $envValue;
      }
    }

    foreach ($runDirCandidates as $candidate) {
      $candidate = rtrim(trim($candidate), DIRECTORY_SEPARATOR);
      if ($candidate !== '') {
        return [
          'id'  => null,
          'dir' => $candidate,
        ];
      }
    }

    if (function_exists('compu_import_run_open') &&
        function_exists('compu_import_get_base_dir') &&
        function_exists('compu_import_mkdir')) {
      $runId = compu_import_run_open($args['source'] ?? 'syscom', $sourceFile);
      $base  = compu_import_get_base_dir();
      $dir   = rtrim($this->trailingslashit($base) . 'run-' . $runId, DIRECTORY_SEPARATOR);
      compu_import_mkdir($dir);
      return [
        'id'  => (int) $runId,
        'dir' => $dir,
      ];
    }

    $this->cli_error('No se pudo determinar el directorio de ejecución (run dir).');
    return ['id' => null, 'dir' => ''];
  }

  private function trailingslashit(string $path): string {
    if (function_exists('trailingslashit')) {
      return trailingslashit($path);
    }
    return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
  }

  private function cli_error(string $message): void {
    if (class_exists('\\WP_CLI')) {
      \WP_CLI::error($message);
    }
    throw new \RuntimeException($message);
  }

  private function cli_success(string $message): void {
    if (class_exists('\\WP_CLI')) {
      \WP_CLI::success($message);
    }
  }

  private function cli_log(string $message): void {
    if (class_exists('\\WP_CLI')) {
      \WP_CLI::log($message);
    }
  }

  /**
   * @param int $runId
   * @param string $stage
   * @param string $level
   * @param string $message
   * @param array<string,mixed> $context
   */
  private function compu_log($runId, $stage, $level, $message, array $context = []): void {
    if (function_exists('compu_import_log')) {
      compu_import_log($runId, $stage, $level, $message, $context);
    }
  }
}
