<?php
if (!defined('ABSPATH')) {
  exit;
}

class Compu_Stage_Validate {
  /**
   * Ejecuta la validación sobre normalized.jsonl conservando las llaves originales.
   *
   * @param array<string,mixed> $args
   */
  public function run($args) {
    $runDir        = $this->resolveRunDirectory(is_array($args) ? $args : []);
    $normalized    = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'normalized.jsonl';
    $headerMapPath = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'header-map.json';
    $validated     = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'validated.jsonl';

    if (!is_file($normalized)) {
      $this->cli_error("Falta normalized.jsonl en {$runDir}. Ejecuta Stage 02 antes de validar.");
    }
    if (!is_file($headerMapPath)) {
      $this->cli_error("Falta header-map.json en {$runDir}. Stage 02 debe generar este archivo.");
    }

    $columns          = $this->loadHeaderMap($headerMapPath);
    $skuField         = $this->detectSkuField($columns);
    $requiredFields   = $this->buildRequiredFields($columns, $skuField);
    $priceFields      = $this->detectPriceFields($columns);
    $stockFields      = $this->detectStockFields($columns);
    $hasPriceOrStock  = !empty($priceFields) || !empty($stockFields);

    $logDir = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
      $this->cli_error("No se pudo crear el directorio de logs: {$logDir}");
    }
    $logPath    = $logDir . DIRECTORY_SEPARATOR . 'stage-03.log';
    $logHandle  = fopen($logPath, 'w');
    if ($logHandle === false) {
      $this->cli_error('No se pudo inicializar el archivo de logs de Stage 03.');
    }

    $inputHandle = fopen($normalized, 'r');
    if ($inputHandle === false) {
      fclose($logHandle);
      $this->cli_error('No se pudo abrir normalized.jsonl para lectura.');
    }

    $outputHandle = fopen($validated, 'w');
    if ($outputHandle === false) {
      fclose($inputHandle);
      fclose($logHandle);
      $this->cli_error('No se pudo crear validated.jsonl para escritura.');
    }

    $rowNumber   = 0;
    $validRows   = 0;
    $invalidRows = 0;
    $skuCounters = [];

    while (($line = fgets($inputHandle)) !== false) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $rowNumber++;

      $decoded = json_decode($line, true);
      if (!is_array($decoded)) {
        $this->log($logHandle, 'error', 'Fila inválida: JSON no se pudo decodificar', [
          'row_number' => $rowNumber,
          'json_error' => json_last_error_msg(),
        ]);
        $invalidRows++;
        continue;
      }

      $errors = [];
      foreach ($requiredFields as $field => $label) {
        if (!array_key_exists($field, $decoded) || $this->isEmpty($decoded[$field])) {
          $errors[] = "Campo obligatorio faltante: {$label}";
        }
      }

      if ($hasPriceOrStock) {
        $hasValue = false;
        foreach ($priceFields as $field) {
          if (array_key_exists($field, $decoded) && !$this->isEmpty($decoded[$field])) {
            $hasValue = true;
            break;
          }
        }
        if (!$hasValue) {
          foreach ($stockFields as $field) {
            if (array_key_exists($field, $decoded) && !$this->isEmpty($decoded[$field])) {
              $hasValue = true;
              break;
            }
          }
        }
        if (!$hasValue) {
          $errors[] = 'La fila no contiene valores en campos de precio o stock.';
        }
      }

      if ($errors) {
        $this->log($logHandle, 'error', 'Fila inválida', [
          'row_number' => $rowNumber,
          'errors'     => $errors,
        ]);
        $invalidRows++;
        continue;
      }

      $skuValue = (string) ($decoded[$skuField] ?? '');
      if ($skuValue !== '') {
        if (!isset($skuCounters[$skuValue])) {
          $skuCounters[$skuValue] = 0;
        }
        $skuCounters[$skuValue]++;
      }

      $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($encoded === false) {
        $this->log($logHandle, 'error', 'No se pudo volver a codificar la fila validada.', [
          'row_number' => $rowNumber,
        ]);
        $invalidRows++;
        continue;
      }

      fwrite($outputHandle, $encoded . "\n");
      $validRows++;
    }

    fclose($inputHandle);
    fclose($outputHandle);

    $duplicates = [];
    foreach ($skuCounters as $value => $count) {
      if ($count > 1) {
        $duplicates[$value] = $count;
        $this->log($logHandle, 'warn', 'SKU duplicado detectado', [
          'sku'   => $value,
          'count' => $count,
        ]);
      }
    }

    $this->log($logHandle, $validRows > 0 ? 'info' : 'warn', 'Resumen de validación', [
      'rows_total'   => $rowNumber,
      'rows_valid'   => $validRows,
      'rows_invalid' => $invalidRows,
      'price_fields' => $priceFields,
      'stock_fields' => $stockFields,
    ]);

    fclose($logHandle);

    $this->exportArtifacts($args, $runDir, $normalized, $validated, $headerMapPath, $logPath);

    if ($duplicates) {
      $this->cli_log('SKU duplicados detectados:');
      foreach ($duplicates as $sku => $count) {
        $this->cli_log("  - {$sku} ({$count})");
      }
    }

    $this->cli_success(sprintf(
      'Stage 03 completado. Filas válidas: %d, inválidas: %d.',
      $validRows,
      $invalidRows
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

    if (function_exists('compu_import_resolve_run_dir')) {
      $resolved = compu_import_resolve_run_dir($args);
      if (is_string($resolved) && $resolved !== '') {
        return rtrim($resolved, DIRECTORY_SEPARATOR);
      }
    }

    $this->cli_error('No se indicó el RUN_DIR para Stage 03.');
    return '';
  }

  /**
   * @return array<int,array{original:string,normalized:string,is_sku_column:bool}>
   */
  private function loadHeaderMap(string $path): array {
    $contents = file_get_contents($path);
    if ($contents === false) {
      $this->cli_error('No se pudo leer header-map.json.');
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded) || !isset($decoded['columns']) || !is_array($decoded['columns'])) {
      $this->cli_error('header-map.json tiene un formato inválido.');
    }

    $columns = [];
    foreach ($decoded['columns'] as $column) {
      if (!is_array($column)) {
        continue;
      }
      $columns[] = [
        'original'      => (string) ($column['original'] ?? ''),
        'normalized'    => (string) ($column['normalized'] ?? ''),
        'is_sku_column' => (bool) ($column['is_sku_column'] ?? false),
      ];
    }
    return $columns;
  }

  /**
   * @param array<int,array{original:string,normalized:string,is_sku_column:bool}> $columns
   */
  private function detectSkuField(array $columns): string {
    foreach ($columns as $column) {
      if ($column['is_sku_column'] && $column['normalized'] !== '') {
        return $column['normalized'];
      }
    }
    return 'SKU';
  }

  /**
   * @param array<int,array{original:string,normalized:string,is_sku_column:bool}> $columns
   * @return array<string,string>
   */
  private function buildRequiredFields(array $columns, string $skuField): array {
    $result = [$skuField => 'SKU'];
    $available = array_column($columns, 'normalized');
    if (in_array('Marca', $available, true)) {
      $result['Marca'] = 'Marca';
    }
    if (in_array('Titulo', $available, true)) {
      $result['Titulo'] = 'Titulo';
    }
    if (in_array('Tipo_de_Cambio', $available, true)) {
      $result['Tipo_de_Cambio'] = 'Tipo_de_Cambio';
    }
    return $result;
  }

  /**
   * @param array<int,array{original:string,normalized:string,is_sku_column:bool}> $columns
   * @return array<int,string>
   */
  private function detectPriceFields(array $columns): array {
    $result = [];
    foreach ($columns as $column) {
      $name = $column['normalized'];
      if ($name === '') {
        continue;
      }
      if ($name === 'Su_Precio') {
        $result[] = $name;
        continue;
      }
      if (strpos($name, 'Precio') === 0) {
        $result[] = $name;
      }
    }
    return array_values(array_unique($result));
  }

  /**
   * @param array<int,array{original:string,normalized:string,is_sku_column:bool}> $columns
   * @return array<int,string>
   */
  private function detectStockFields(array $columns): array {
    $result = [];
    foreach ($columns as $column) {
      $name = $column['normalized'];
      if ($name === '') {
        continue;
      }
      if (stripos($name, 'Stock_') === 0 || $name === 'Stock' || $name === 'Existencias') {
        $result[] = $name;
      }
    }
    return array_values(array_unique($result));
  }

  private function isEmpty($value): bool {
    if ($value === null) {
      return true;
    }
    if (is_string($value)) {
      return trim($value) === '';
    }
    if (is_array($value)) {
      return count($value) === 0;
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
   */
  private function exportArtifacts(array $args, string $runDir, string $normalized, string $validated, string $headerMap, string $logPath): void {
    $runId = $this->resolveRunId($args, $runDir);
    $root  = dirname(__DIR__, 4);
    $targetBase = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'runs' . DIRECTORY_SEPARATOR . $runId . DIRECTORY_SEPARATOR . 'step-03';

    if (!is_dir($targetBase) && !mkdir($targetBase, 0777, true) && !is_dir($targetBase)) {
      $this->cli_error("No se pudo crear el directorio de artefactos: {$targetBase}");
    }

    $this->copyFile($normalized, $targetBase . DIRECTORY_SEPARATOR . 'normalized.jsonl');
    $this->copyFile($validated, $targetBase . DIRECTORY_SEPARATOR . 'validated.jsonl');
    $this->copyFile($headerMap, $targetBase . DIRECTORY_SEPARATOR . 'header-map.json');

    $logsTarget = $targetBase . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsTarget) && !mkdir($logsTarget, 0777, true) && !is_dir($logsTarget)) {
      $this->cli_error("No se pudo crear el directorio de logs en artefactos: {$logsTarget}");
    }
    $this->copyFile($logPath, $logsTarget . DIRECTORY_SEPARATOR . 'stage-03.log');
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

  private function cli_log(string $message): void {
    if (class_exists('\\WP_CLI')) {
      \WP_CLI::log($message);
    } else {
      echo $message . "\n";
    }
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
