<?php
if (!defined('ABSPATH')) {
  exit;
}

class Compu_Stage_Finalize {
  /**
   * Empaqueta resolved.jsonl en artefactos finales listos para revisión.
   *
   * @param array<string,mixed> $args
   */
  public function run($args) {
    $runDir   = $this->resolveRunDirectory(is_array($args) ? $args : []);
    $resolved = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'resolved.jsonl';
    $logDir   = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'logs';
    $finalDir = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'final';

    if (!is_file($resolved)) {
      $this->cli_error("Falta resolved.jsonl en {$runDir}. Ejecuta Stage 04 primero.");
    }

    if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
      $this->cli_error("No se pudo crear el directorio de logs: {$logDir}");
    }
    if (!is_dir($finalDir) && !mkdir($finalDir, 0777, true) && !is_dir($finalDir)) {
      $this->cli_error("No se pudo crear el directorio final: {$finalDir}");
    }

    $logPath   = $logDir . DIRECTORY_SEPARATOR . 'stage-06.log';
    $logHandle = fopen($logPath, 'w');
    if ($logHandle === false) {
      $this->cli_error('No se pudo iniciar el log de Stage 06.');
    }

    $inputHandle = fopen($resolved, 'r');
    if ($inputHandle === false) {
      fclose($logHandle);
      $this->cli_error('No se pudo abrir resolved.jsonl para lectura.');
    }

    $importPath  = $finalDir . DIRECTORY_SEPARATOR . 'import-ready.csv';
    $skippedPath = $finalDir . DIRECTORY_SEPARATOR . 'skipped.csv';
    $summaryPath = $finalDir . DIRECTORY_SEPARATOR . 'summary.json';

    $importHandle  = fopen($importPath, 'w');
    $skippedHandle = fopen($skippedPath, 'w');

    if ($importHandle === false || $skippedHandle === false) {
      if ($importHandle !== false) {
        fclose($importHandle);
      }
      if ($skippedHandle !== false) {
        fclose($skippedHandle);
      }
      fclose($inputHandle);
      fclose($logHandle);
      $this->cli_error('No se pudieron crear los CSV de salida de Stage 06.');
    }

    $baseColumns = [
      'sku',
      'Nombre',
      'Marca',
      'Modelo',
      'Titulo',
      'Su_Precio',
      'Tipo_de_Cambio',
      'ID_Menu_Nvl_1',
      'ID_Menu_Nvl_2',
      'ID_Menu_Nvl_3',
      'Almacen_15',
      'Almacen_15_Tijuana',
      'Descripcion',
      'Imagen_Principal',
    ];

    $extraColumns = ['stock_total', 'price_source', 'resolve_status', 'resolve_reason'];

    fputcsv($importHandle, array_merge($baseColumns, $extraColumns), ',', '"', '\\');
    fputcsv($skippedHandle, array_merge($baseColumns, ['stock_total', 'resolve_status', 'resolve_reason', 'skip_reason']), ',', '"', '\\');

    $rowsTotal        = 0;
    $rowsImportable   = 0;
    $rowsSkipped      = 0;
    $skipReasonsCount = [];
    $priceSources     = [];

    while (($line = fgets($inputHandle)) !== false) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $rowsTotal++;

      $decoded = json_decode($line, true);
      if (!is_array($decoded)) {
        $this->log($logHandle, 'error', 'Fila inválida en resolved.jsonl', [
          'row_number' => $rowsTotal,
          'json_error' => json_last_error_msg(),
        ]);
        $rowsSkipped++;
        $this->writeSkippedRow(
          $skippedHandle,
          $baseColumns,
          [],
          [
            'stock_details'  => ['almacen_15' => 0.0, 'almacen_15_tijuana' => 0.0, 'total' => 0.0],
            'resolve_status' => 'error',
            'resolve_reason' => 'invalid_json',
          ],
          'invalid_json'
        );
        $this->incrementReason($skipReasonsCount, 'invalid_json');
        continue;
      }

      $evaluation = $this->evaluateRow($decoded);
      $priceSource = $evaluation['price_source'];
      if ($priceSource !== null) {
        $this->incrementReason($priceSources, $priceSource);
      }

      if ($evaluation['importable']) {
        $rowsImportable++;
        $rowData = $this->prepareRowForCsv($decoded, $baseColumns, $evaluation['final_price'], $evaluation['stock_details']);
        $rowData['price_source']   = $priceSource ?? '';
        $rowData['resolve_status'] = $evaluation['resolve_status'];
        $rowData['resolve_reason'] = $evaluation['resolve_reason'];
        fputcsv($importHandle, $this->flattenRow($baseColumns, $extraColumns, $rowData), ',', '"', '\\');
      } else {
        $rowsSkipped++;
        $reasonList = $evaluation['reasons'];
        foreach ($reasonList as $reason) {
          $this->incrementReason($skipReasonsCount, $reason);
        }
        $this->log($logHandle, 'warn', 'Fila no importable', [
          'row_number' => $rowsTotal,
          'reasons'    => $reasonList,
          'sku'        => $decoded['sku'] ?? null,
        ]);
        $this->writeSkippedRow(
          $skippedHandle,
          $baseColumns,
          $decoded,
          $evaluation,
          implode(';', $reasonList)
        );
      }
    }

    fclose($inputHandle);
    fclose($importHandle);
    fclose($skippedHandle);

    $summary = [
      'generated_at'    => gmdate('c'),
      'total'           => $rowsTotal,
      'import_ready'    => $rowsImportable,
      'skipped'         => $rowsSkipped,
      'skipped_reasons' => $this->sortReasons($skipReasonsCount),
      'price_sources'   => $this->sortReasons($priceSources),
      'files'           => [
        'import_ready' => $importPath,
        'skipped'      => $skippedPath,
        'summary'      => $summaryPath,
      ],
    ];

    file_put_contents($summaryPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $logSummary = $summary;
    $logSummary['log_path'] = $logPath;
    $this->log($logHandle, 'info', 'Resumen Stage 06', $logSummary);
    fclose($logHandle);

    $this->exportArtifacts($args, $runDir, $importPath, $skippedPath, $summaryPath, $logPath, $summary);

    $this->cli_success(sprintf(
      'Stage 06 completado. Importables: %d, omitidos: %d.',
      $rowsImportable,
      $rowsSkipped
    ));
  }

  /**
   * @param array<string,mixed> $decoded
   * @return array{
   *   importable:bool,
   *   reasons:array<int,string>,
   *   final_price:?float,
   *   price_source:?string,
   *   stock_total:float,
   *   resolve_status:string,
   *   resolve_reason:string
   * }
   */
  private function evaluateRow(array $decoded): array {
    $reasons = [];

    $sku    = $this->stringValue($decoded['sku'] ?? null);
    $titulo = $this->stringValue($decoded['Titulo'] ?? null);
    $status = strtolower($this->stringValue($decoded['resolve_status'] ?? 'ok'));
    $reason = $this->stringValue($decoded['resolve_reason'] ?? '');

    $stockData = $this->extractStockData($decoded);
    $priceData = $this->extractPrice($decoded);

    if ($sku === '') {
      $reasons[] = 'missing_sku';
    }
    if ($titulo === '') {
      $reasons[] = 'missing_titulo';
    }

    if ($status === 'blocked_lvl1') {
      $reasons[] = 'blocked_lvl1';
    } elseif ($status === 'error') {
      $reasons[] = $reason !== '' ? 'resolve_error:' . $reason : 'resolve_error';
    }

    if ($priceData['value'] === null && $stockData['total'] <= 0) {
      $reasons[] = 'missing_price_and_stock';
    }

    $importable = empty($reasons);

    return [
      'importable'     => $importable,
      'reasons'        => $reasons,
      'final_price'    => $priceData['value'],
      'price_source'   => $priceData['source'],
      'stock_total'    => $stockData['total'],
      'stock_details'  => $stockData,
      'resolve_status' => $status !== '' ? $status : 'ok',
      'resolve_reason' => $reason,
    ];
  }

  /**
   * @param array<string,mixed> $decoded
   * @return array{value:?float,source:?string}
   */
  private function extractPrice(array $decoded): array {
    $candidates = [
      'Su_Precio',
      'Precio_Especial',
      'Precio_Lista',
    ];
    foreach ($candidates as $field) {
      if (!array_key_exists($field, $decoded)) {
        continue;
      }
      $value = $this->toFloat($decoded[$field]);
      if ($value !== null && $value > 0) {
        return ['value' => $value, 'source' => $field];
      }
    }
    return ['value' => null, 'source' => null];
  }

  /**
   * @param array<string,mixed> $decoded
   * @return array{almacen_15:float,almacen_15_tijuana:float,total:float}
   */
  private function extractStockData(array $decoded): array {
    $almacen15 = $this->toFloat($decoded['Almacen_15'] ?? null);
    $almacen15 = $almacen15 !== null ? max(0.0, $almacen15) : 0.0;

    $almacenTijuana = $this->toFloat($decoded['Almacen_15_Tijuana'] ?? null);
    $almacenTijuana = $almacenTijuana !== null ? max(0.0, $almacenTijuana) : 0.0;

    if ($almacen15 === 0.0 && $almacenTijuana === 0.0) {
      $fallback = max(0.0, $this->sumStockFields($decoded));
      if ($fallback > 0) {
        $almacen15 = $fallback;
      }
    }

    $total = max(0.0, $almacen15 + $almacenTijuana);

    return [
      'almacen_15' => $almacen15,
      'almacen_15_tijuana' => $almacenTijuana,
      'total' => $total,
    ];
  }

  /**
   * @param array<string,mixed> $row
   * @param array<int,string> $columns
   * @param array{almacen_15:float,almacen_15_tijuana:float,total:float} $stockDetails
   */
  private function prepareRowForCsv(array $row, array $columns, ?float $finalPrice, array $stockDetails): array {
    $prepared = [];
    $marca  = $this->stringValue($row['Marca'] ?? '');
    $modelo = $this->stringValue($row['Modelo'] ?? '');
    $titulo = $this->stringValue($row['Titulo'] ?? '');

    foreach ($columns as $column) {
      switch ($column) {
        case 'Nombre':
          $prepared[$column] = $this->buildNombre($marca, $modelo, $titulo);
          break;
        case 'Almacen_15':
          $prepared[$column] = $this->formatNumber($stockDetails['almacen_15'] ?? 0.0);
          break;
        case 'Almacen_15_Tijuana':
          $prepared[$column] = $this->formatNumber($stockDetails['almacen_15_tijuana'] ?? 0.0);
          break;
        default:
          $prepared[$column] = $this->stringValue($row[$column] ?? '');
          break;
      }
    }

    if ($finalPrice !== null) {
      $prepared['Su_Precio'] = $this->formatPrice($finalPrice);
    }

    $prepared['stock_total'] = $this->formatNumber($stockDetails['total'] ?? 0.0);

    return $prepared;
  }

  /**
   * @param array<int,string> $baseColumns
   * @param array<int,string> $extraColumns
   * @param array<string,string> $data
   * @return array<int,string>
   */
  private function flattenRow(array $baseColumns, array $extraColumns, array $data): array {
    $row = [];
    foreach ($baseColumns as $column) {
      $row[] = $data[$column] ?? '';
    }
    foreach ($extraColumns as $column) {
      $row[] = $data[$column] ?? '';
    }
    return $row;
  }

  /**
   * @param resource $handle
   * @param array<int,string> $columns
   * @param array<string,mixed> $row
   * @param array{stock_details:array{almacen_15:float,almacen_15_tijuana:float,total:float},resolve_status:string,resolve_reason:string} $evaluation
   */
  private function writeSkippedRow($handle, array $columns, array $row, array $evaluation, string $reason): void {
    $baseData = $this->prepareRowForCsv($row, $columns, null, $evaluation['stock_details']);
    $baseData['resolve_status'] = $evaluation['resolve_status'];
    $baseData['resolve_reason'] = $evaluation['resolve_reason'];
    $baseData['skip_reason']    = $reason;
    fputcsv($handle, $this->flattenRow($columns, ['stock_total', 'resolve_status', 'resolve_reason', 'skip_reason'], $baseData), ',', '"', '\\');
  }

  private function formatPrice(float $value): string {
    return number_format($value, 2, '.', '');
  }

  private function formatNumber(float $value): string {
    return rtrim(rtrim(sprintf('%.4f', $value), '0'), '.');
  }

  private function buildNombre(string $marca, string $modelo, string $titulo): string {
    $parts = [];
    foreach ([$marca, $modelo, $titulo] as $part) {
      $part = trim($part);
      if ($part !== '') {
        $parts[] = $part;
      }
    }
    if (empty($parts)) {
      return '';
    }
    $nombre = trim(implode(' ', $parts));
    $normalized = preg_replace('/\s+/', ' ', $nombre);
    return $normalized === null ? $nombre : trim($normalized);
  }

  private function incrementReason(array &$bucket, string $reason): void {
    if (!isset($bucket[$reason])) {
      $bucket[$reason] = 0;
    }
    $bucket[$reason]++;
  }

  /**
   * @param array<string,int> $reasons
   * @return array<string,int>
   */
  private function sortReasons(array $reasons): array {
    arsort($reasons);
    return $reasons;
  }

  private function stringValue($value): string {
    if ($value === null) {
      return '';
    }
    return trim((string) $value);
  }

  private function toFloat($value): ?float {
    if ($value === null || $value === '') {
      return null;
    }
    if (is_numeric($value)) {
      return (float) $value;
    }
    if (is_string($value)) {
      $clean = preg_replace('/[^0-9.,-]/', '', $value);
      if ($clean === null || $clean === '' || $clean === '-') {
        return null;
      }
      $clean = str_replace(',', '', $clean);
      if ($clean === '' || $clean === '-' || $clean === null) {
        return null;
      }
      return (float) $clean;
    }
    return null;
  }

  /**
   * @param array<string,mixed> $row
   */
  private function sumStockFields(array $row): float {
    $total = 0.0;
    foreach ($row as $key => $value) {
      if (strpos((string) $key, 'Stock_') !== 0) {
        continue;
      }
      $numeric = $this->toFloat($value);
      if ($numeric !== null) {
        $total += $numeric;
      }
    }
    return round($total, 4);
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

    $this->cli_error('No se indicó RUN_DIR para Stage 06.');
    return '';
  }

  /**
   * @param array<string,mixed> $args
   * @param array<string,mixed> $summary
   */
  private function exportArtifacts(array $args, string $runDir, string $importPath, string $skippedPath, string $summaryPath, string $logPath, array $summary): void {
    $runId = $this->resolveRunId($args, $runDir);
    $root  = dirname(__DIR__, 4);
    $targetBase = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'runs' . DIRECTORY_SEPARATOR . $runId . DIRECTORY_SEPARATOR . 'step-06';

    if (!is_dir($targetBase) && !mkdir($targetBase, 0777, true) && !is_dir($targetBase)) {
      $this->cli_error("No se pudo crear el directorio de artefactos: {$targetBase}");
    }

    $finalDir = $targetBase . DIRECTORY_SEPARATOR . 'final';
    if (!is_dir($finalDir) && !mkdir($finalDir, 0777, true) && !is_dir($finalDir)) {
      $this->cli_error("No se pudo crear el directorio final de artefactos: {$finalDir}");
    }

    $this->copyFile($importPath, $finalDir . DIRECTORY_SEPARATOR . 'import-ready.csv');
    $this->copyFile($skippedPath, $finalDir . DIRECTORY_SEPARATOR . 'skipped.csv');
    $this->copyFile($summaryPath, $finalDir . DIRECTORY_SEPARATOR . 'summary.json');

    $logsTarget = $targetBase . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsTarget) && !mkdir($logsTarget, 0777, true) && !is_dir($logsTarget)) {
      $this->cli_error("No se pudo crear el directorio de logs en artefactos: {$logsTarget}");
    }
    $this->copyFile($logPath, $logsTarget . DIRECTORY_SEPARATOR . 'stage-06.log');

    $readmePath = $targetBase . DIRECTORY_SEPARATOR . 'README.md';
    $this->generateReadme($readmePath, $summary);
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
   * @param array<string,mixed> $summary
   */
  private function generateReadme(string $path, array $summary): void {
    $lines   = [];
    $lines[] = '# Stage 06 - Empaquetado final';
    $lines[] = '';
    $lines[] = sprintf('* Filas procesadas: %d', (int) ($summary['total'] ?? 0));
    $lines[] = sprintf('* Importables: %d', (int) ($summary['import_ready'] ?? 0));
    $lines[] = sprintf('* Omitidas: %d', (int) ($summary['skipped'] ?? 0));
    if (isset($summary['skipped_reasons']) && is_array($summary['skipped_reasons'])) {
      $lines[] = '* Motivos principales de omisión:';
      $count = 0;
      foreach ($summary['skipped_reasons'] as $reason => $qty) {
        $lines[] = sprintf('  * %s: %d', $reason, (int) $qty);
        $count++;
        if ($count >= 5) {
          break;
        }
      }
    }
    $lines[] = '';
    $lines[] = 'Los CSV incluidos están listos para revisión manual o importación simulada en sandbox.';

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
