<?php
if (!defined('ABSPATH')) {
  exit;
}

class Compu_Stage_Resolve_Map {
  private const BLOCKED_LVL1_VALUES = ['---', '25'];

  /**
   * @var array<string,bool>
   */
  private $sucursalColumns = [
    'Chihuahua' => true,
    'Cd_Juarez' => true,
    'Guadalajara' => true,
    'Los_Mochis' => true,
    'Merida' => true,
    'Mexico_Norte' => true,
    'Mexico_Sur' => true,
    'Monterrey' => true,
    'Puebla' => true,
    'Queretaro' => true,
    'Villahermosa' => true,
    'Leon' => true,
    'Hermosillo' => true,
    'San_Luis_Potosi' => true,
    'Torreon' => true,
    'Chihuahua_CEDIS' => true,
    'Toluca' => true,
    'Tijuana' => true,
  ];

  /**
   * Ejecuta la resolución incorporando campos de almacén y bloqueos por categoría de nivel 1.
   *
   * @param array<string,mixed> $args
   */
  public function run($args) {
    $runDir   = $this->resolveRunDirectory(is_array($args) ? $args : []);
    $validated = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'validated.jsonl';
    $resolved  = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'resolved.jsonl';
    $logDir    = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'logs';
    $metricsOut = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'stage-04.metrics.json';

    if (!is_file($validated)) {
      $this->cli_error("Falta validated.jsonl en {$runDir}. Ejecuta Stage 03 antes de continuar.");
    }

    if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
      $this->cli_error("No se pudo crear el directorio de logs: {$logDir}");
    }

    $logPath   = $logDir . DIRECTORY_SEPARATOR . 'stage-04.log';
    $logHandle = fopen($logPath, 'w');
    if ($logHandle === false) {
      $this->cli_error('No se pudo iniciar el log de Stage 04.');
    }

    $inputHandle = fopen($validated, 'r');
    if ($inputHandle === false) {
      fclose($logHandle);
      $this->cli_error('No se pudo abrir validated.jsonl para lectura.');
    }

    $outputHandle = fopen($resolved, 'w');
    if ($outputHandle === false) {
      fclose($inputHandle);
      fclose($logHandle);
      $this->cli_error('No se pudo crear resolved.jsonl para escritura.');
    }

    $rowsIn         = 0;
    $rowsOut        = 0;
    $blockedLvl1    = 0;
    $statusCounts   = [];

    while (($line = fgets($inputHandle)) !== false) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $rowsIn++;

      $decoded = json_decode($line, true);
      if (!is_array($decoded)) {
        $this->log($logHandle, 'error', 'Fila inválida: JSON no se pudo decodificar.', [
          'row_number' => $rowsIn,
          'json_error' => json_last_error_msg(),
        ]);
        continue;
      }

      $stockData = $this->calculateStocks($decoded);
      $decoded['Almacen_15'] = $stockData['almacen_15'];
      $decoded['Almacen_15_Tijuana'] = $stockData['almacen_15_tijuana'];
      $decoded['stock_total'] = $stockData['total'];

      $idMenuLvl1 = $this->stringValue($decoded['ID_Menu_Nvl_1'] ?? null);
      $status     = strtolower($this->stringValue($decoded['resolve_status'] ?? ''));
      $reason     = $this->stringValue($decoded['resolve_reason'] ?? '');

      if ($this->isBlockedLvl1($idMenuLvl1)) {
        $status  = 'blocked_lvl1';
        $reason  = 'ID_Menu_Nvl_1 in {---,25}';
        $blockedLvl1++;
      } elseif ($status === '') {
        $status = 'ok';
      }

      $decoded['resolve_status'] = $status;
      if ($reason !== '') {
        $decoded['resolve_reason'] = $reason;
      } elseif (isset($decoded['resolve_reason'])) {
        unset($decoded['resolve_reason']);
      }

      if (!isset($statusCounts[$status])) {
        $statusCounts[$status] = 0;
      }
      $statusCounts[$status]++;

      $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($encoded === false) {
        $this->log($logHandle, 'error', 'No se pudo codificar la fila resuelta.', [
          'row_number' => $rowsIn,
        ]);
        continue;
      }

      fwrite($outputHandle, $encoded . "\n");
      $rowsOut++;
    }

    fclose($inputHandle);
    fclose($outputHandle);

    $summary = [
      'rows_in'       => $rowsIn,
      'rows_out'      => $rowsOut,
      'blocked_lvl1'  => $blockedLvl1,
      'status_counts' => $statusCounts,
      'log_path'      => $logPath,
      'resolved_path' => $resolved,
    ];

    $this->log($logHandle, 'info', 'Resumen Stage 04', $summary);
    fclose($logHandle);

    $metrics = array_merge($summary, [
      'generated_at' => gmdate('c'),
    ]);

    file_put_contents($metricsOut, json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->exportArtifacts($args, $runDir, $resolved, $logPath, $metricsOut, $metrics);

    $this->cli_success(sprintf(
      'Stage 04 completado. Filas procesadas: %d, bloqueadas lvl1: %d.',
      $rowsIn,
      $blockedLvl1
    ));
  }

  /**
   * @param array<string,mixed> $args
   */
  private function resolveRunDirectory(array $args): string {
    $candidates = [];
    foreach (['run-dir', 'run_dir', 'runDir', 'dir', 'path'] as $key) {
      if (!empty($args[$key])) {
        $candidates[] = (string) $args[$key];
      }
    }
    foreach (['RUN_DIR', 'RUN_PATH'] as $envKey) {
      $envValue = getenv($envKey);
      if ($envValue !== false && $envValue !== '') {
        $candidates[] = (string) $envValue;
      }
    }

    foreach ($candidates as $candidate) {
      $candidate = rtrim(trim($candidate), DIRECTORY_SEPARATOR);
      if ($candidate !== '') {
        return $candidate;
      }
    }

    $this->cli_error('No se indicó RUN_DIR para Stage 04.');
    return '';
  }

  /**
   * @param array<string,mixed> $row
   * @return array{almacen_15:float,almacen_15_tijuana:float,total:float}
   */
  private function calculateStocks(array $row): array {
    $almacen15 = 0.0;
    $almacen15Tijuana = 0.0;

    foreach ($row as $key => $value) {
      $numeric = $this->toNumeric($value);
      if ($numeric <= 0) {
        continue;
      }

      if (isset($this->sucursalColumns[$key])) {
        if ($key === 'Tijuana') {
          $almacen15Tijuana += $numeric;
        } else {
          $almacen15 += $numeric;
        }
        continue;
      }

      if (strpos((string) $key, 'Stock_') === 0) {
        if (strcasecmp((string) $key, 'Stock_Tijuana') === 0) {
          $almacen15Tijuana += $numeric;
        } else {
          $almacen15 += $numeric;
        }
      }
    }

    return [
      'almacen_15' => $this->roundNumber($almacen15),
      'almacen_15_tijuana' => $this->roundNumber($almacen15Tijuana),
      'total' => $this->roundNumber($almacen15 + $almacen15Tijuana),
    ];
  }

  private function toNumeric($value): float {
    if ($value === null || $value === '') {
      return 0.0;
    }
    if (is_int($value) || is_float($value)) {
      $numeric = (float) $value;
    } elseif (is_string($value)) {
      $clean = preg_replace('/[^0-9.,-]/', '', $value);
      if ($clean === null || $clean === '' || $clean === '-') {
        return 0.0;
      }
      $clean = str_replace(',', '', $clean);
      if ($clean === '' || $clean === '-' || !is_numeric($clean)) {
        return 0.0;
      }
      $numeric = (float) $clean;
    } else {
      return 0.0;
    }

    if (!is_finite($numeric) || $numeric <= 0) {
      return 0.0;
    }

    return $numeric;
  }

  private function roundNumber(float $value): float {
    return (float) sprintf('%.4f', max(0.0, $value));
  }

  private function stringValue($value): string {
    if ($value === null) {
      return '';
    }
    return trim((string) $value);
  }

  private function isBlockedLvl1(string $value): bool {
    if ($value === '') {
      return false;
    }
    foreach (self::BLOCKED_LVL1_VALUES as $blocked) {
      if (strcasecmp($value, $blocked) === 0) {
        return true;
      }
    }
    return false;
  }

  /**
   * @param resource $handle
   * @param array<string,mixed> $context
   */
  private function log($handle, string $level, string $message, array $context = []): void {
    $payload = [
      'timestamp' => gmdate('c'),
      'level'     => strtoupper($level),
      'message'   => $message,
    ];
    if (!empty($context)) {
      $payload['context'] = $context;
    }
    fwrite($handle, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
  }

  /**
   * @param array<string,mixed> $args
   * @param array<string,mixed> $metrics
   */
  private function exportArtifacts(array $args, string $runDir, string $resolved, string $logPath, string $metricsPath, array $metrics): void {
    $runId = $this->resolveRunId($args, $runDir);
    $root  = dirname(__DIR__, 4);
    $targetBase = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'runs' . DIRECTORY_SEPARATOR . $runId . DIRECTORY_SEPARATOR . 'step-04';

    if (!is_dir($targetBase) && !mkdir($targetBase, 0777, true) && !is_dir($targetBase)) {
      $this->cli_error("No se pudo crear el directorio de artefactos: {$targetBase}");
    }

    $this->copyFile($resolved, $targetBase . DIRECTORY_SEPARATOR . 'resolved.jsonl');

    $logsTarget = $targetBase . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsTarget) && !mkdir($logsTarget, 0777, true) && !is_dir($logsTarget)) {
      $this->cli_error("No se pudo crear el directorio de logs en artefactos: {$logsTarget}");
    }
    $this->copyFile($logPath, $logsTarget . DIRECTORY_SEPARATOR . 'stage-04.log');

    $this->copyFile($metricsPath, $targetBase . DIRECTORY_SEPARATOR . 'metrics.json');

    $readmePath = $targetBase . DIRECTORY_SEPARATOR . 'README.md';
    $this->generateReadme($readmePath, $metrics);
  }

  private function copyFile(string $source, string $destination): void {
    if (!is_file($source)) {
      $this->cli_error("No se encontró el archivo requerido para exportar: {$source}");
    }
    if (!copy($source, $destination)) {
      $this->cli_error("No se pudo copiar {$source} a {$destination}");
    }
  }

  /**
   * @param array<string,mixed> $args
   */
  private function resolveRunId(array $args, string $runDir): string {
    foreach (['run-id', 'run_id', 'runId', 'id'] as $key) {
      if (!empty($args[$key])) {
        return $this->sanitizeRunId((string) $args[$key]);
      }
    }
    $base = basename($runDir);
    if ($base !== '') {
      return $this->sanitizeRunId($base);
    }
    return 'run';
  }

  private function sanitizeRunId(string $value): string {
    $value = trim($value);
    if ($value === '') {
      return 'run';
    }
    $value = preg_replace('/[^A-Za-z0-9_\-]/', '_', $value);
    return $value === '' ? 'run' : $value;
  }

  /**
   * @param array<string,mixed> $metrics
   */
  private function generateReadme(string $path, array $metrics): void {
    $lines   = [];
    $lines[] = '# Stage 04 - Resolución y cálculo de almacenes';
    $lines[] = '';
    $lines[] = sprintf('* Filas procesadas: %d', (int) ($metrics['rows_in'] ?? 0));
    $lines[] = sprintf('* Filas escritas: %d', (int) ($metrics['rows_out'] ?? 0));
    $lines[] = sprintf('* Bloqueadas nivel 1: %d', (int) ($metrics['blocked_lvl1'] ?? 0));
    if (isset($metrics['status_counts']) && is_array($metrics['status_counts'])) {
      $lines[] = '* Conteo por estado:';
      foreach ($metrics['status_counts'] as $status => $count) {
        $lines[] = sprintf('  * %s: %d', $status, (int) $count);
      }
    }
    $lines[] = '';
    $lines[] = 'Los archivos de este directorio corresponden al resultado del Stage 04 en modo sandbox.';

    file_put_contents($path, implode("\n", $lines) . "\n");
  }

  private function cli_success(string $message): void {
    if (class_exists('\\WP_CLI')) {
      \WP_CLI::success($message);
    } else {
      echo $message . "\n";
    }
  }

  private function cli_error(string $message): void {
    if (class_exists('\\WP_CLI')) {
      \WP_CLI::error($message);
    }
    throw new \RuntimeException($message);
  }
}
