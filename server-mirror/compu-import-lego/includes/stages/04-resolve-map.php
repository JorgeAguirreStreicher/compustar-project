<?php
if (!defined('ABSPATH')) {
  exit;
}

class Compu_Stage_Resolve_Map {
  /**
   * Ejecuta la resolución de categorías a partir de validated.jsonl.
   *
   * @param array<string,mixed> $args
   */
  public function run($args) {
    $runDir     = $this->resolveRunDirectory(is_array($args) ? $args : []);
    $validated  = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'validated.jsonl';
    $resolved   = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'resolved.jsonl';
    $logDir     = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'logs';
    $metricsOut = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'stage-04.metrics.json';

    if (!is_file($validated)) {
      $this->cli_error("Falta validated.jsonl en {$runDir}. Ejecuta Stage 03 primero.");
    }

    $menuMapPath = $this->resolveMenuMapPath(is_array($args) ? $args : []);
    $menuMap     = $this->loadMenuMap($menuMapPath);

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

    $rowsTotal       = 0;
    $rowsResolved    = 0;
    $statusCounts    = ['ok' => 0, 'warn' => 0, 'error' => 0];
    $missingMapLvl1  = 0;
    $missingMapLvl2  = 0;
    $missingMapLvl3  = 0;

    while (($line = fgets($inputHandle)) !== false) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $rowsTotal++;

      $decoded = json_decode($line, true);
      if (!is_array($decoded)) {
        $this->log($logHandle, 'error', 'Fila inválida: no se pudo decodificar JSON.', [
          'row_number' => $rowsTotal,
          'json_error' => json_last_error_msg(),
        ]);
        $statusCounts['error']++;
        continue;
      }

      $ids = [
        'lvl1' => $this->normalizeId($decoded['ID_Menu_Nvl_1'] ?? null),
        'lvl2' => $this->normalizeId($decoded['ID_Menu_Nvl_2'] ?? null),
        'lvl3' => $this->normalizeId($decoded['ID_Menu_Nvl_3'] ?? null),
      ];

      $categoryResolution = $this->resolveCategories($menuMap, $ids);
      $stockTotal         = $this->sumStockFields($decoded);

      $decoded['cat_lvl1_id'] = $categoryResolution['cat_lvl1_id'];
      $decoded['cat_lvl2_id'] = $categoryResolution['cat_lvl2_id'];
      $decoded['cat_lvl3_id'] = $categoryResolution['cat_lvl3_id'];
      $decoded['stock_total'] = $stockTotal;
      $decoded['resolve_status'] = $categoryResolution['status'];

      if ($categoryResolution['reason'] !== null && $categoryResolution['reason'] !== '') {
        $decoded['resolve_reason'] = $categoryResolution['reason'];
      } elseif (isset($decoded['resolve_reason'])) {
        unset($decoded['resolve_reason']);
      }

      if ($categoryResolution['status'] === 'error') {
        $statusCounts['error']++;
      } elseif ($categoryResolution['status'] === 'warn') {
        $statusCounts['warn']++;
      } else {
        $statusCounts['ok']++;
      }

      if ($categoryResolution['missing_lvl1']) {
        $missingMapLvl1++;
      }
      if ($categoryResolution['missing_lvl2']) {
        $missingMapLvl2++;
      }
      if ($categoryResolution['missing_lvl3']) {
        $missingMapLvl3++;
      }

      if ($categoryResolution['status'] !== 'ok') {
        $this->log($logHandle, $categoryResolution['status'], 'Fila con incidencias en el mapeo.', [
          'row_number' => $rowsTotal,
          'ids'        => $ids,
          'reason'     => $categoryResolution['reason'],
        ]);
      }

      $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($encoded === false) {
        $this->log($logHandle, 'error', 'No se pudo codificar la fila resuelta.', [
          'row_number' => $rowsTotal,
        ]);
        $statusCounts['error']++;
        continue;
      }

      fwrite($outputHandle, $encoded . "\n");
      $rowsResolved++;
    }

    fclose($inputHandle);
    fclose($outputHandle);

    $this->log($logHandle, 'info', 'Resumen Stage 04', [
      'rows_total'      => $rowsTotal,
      'rows_resolved'   => $rowsResolved,
      'status_counts'   => $statusCounts,
      'missing_map_lvl' => [
        'lvl1' => $missingMapLvl1,
        'lvl2' => $missingMapLvl2,
        'lvl3' => $missingMapLvl3,
      ],
      'menu_map_path'   => $menuMapPath,
    ]);

    fclose($logHandle);

    $metrics = [
      'generated_at'     => gmdate('c'),
      'menu_map_path'    => $menuMapPath,
      'rows_total'       => $rowsTotal,
      'rows_resolved'    => $rowsResolved,
      'status_counts'    => $statusCounts,
      'missing_map_lvl1' => $missingMapLvl1,
      'missing_map_lvl2' => $missingMapLvl2,
      'missing_map_lvl3' => $missingMapLvl3,
    ];

    file_put_contents($metricsOut, json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->exportArtifacts($args, $runDir, $resolved, $logPath, $metricsOut, $metrics);

    $this->cli_success(sprintf(
      'Stage 04 completado. Filas: %d (ok: %d, warn: %d, error: %d).',
      $rowsTotal,
      $statusCounts['ok'],
      $statusCounts['warn'],
      $statusCounts['error']
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
   * @param array<string,mixed> $args
   */
  private function resolveMenuMapPath(array $args): string {
    $candidates = [];
    foreach (['menu-map', 'menu_map', 'map', 'config'] as $key) {
      if (!empty($args[$key])) {
        $candidates[] = (string) $args[$key];
      }
    }

    if (empty($candidates)) {
      $root = dirname(__DIR__, 4);
      $candidates[] = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'menu-map.json';
    }

    foreach ($candidates as $candidate) {
      $candidate = trim($candidate);
      if ($candidate === '') {
        continue;
      }
      if (is_file($candidate)) {
        return $candidate;
      }
    }

    $this->cli_error('No se encontró el archivo de mapeo de menús (menu-map.json).');
    return '';
  }

  /**
   * @return array<string,array{cat_id:int|null,children:array}>
   */
  private function loadMenuMap(string $path): array {
    $contents = file_get_contents($path);
    if ($contents === false) {
      $this->cli_error("No se pudo leer el archivo de mapeo: {$path}");
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
      $this->cli_error('menu-map.json tiene un formato inválido.');
    }

    if ($this->isFlatMapping($decoded)) {
      return $this->buildNestedMapFromFlat($decoded);
    }

    return $this->sanitizeNestedMap($decoded);
  }

  /**
   * @param mixed $data
   */
  private function isFlatMapping($data): bool {
    if (!is_array($data)) {
      return false;
    }
    if ($data === []) {
      return false;
    }
    $first = reset($data);
    return is_array($first) && array_key_exists('ID_Menu_Nvl_1', $first);
  }

  /**
   * @param array<int,array<string,mixed>> $rows
   * @return array<string,array{cat_id:int|null,children:array}>
   */
  private function buildNestedMapFromFlat(array $rows): array {
    $result = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $lvl1 = $this->normalizeId($row['ID_Menu_Nvl_1'] ?? null);
      $lvl2 = $this->normalizeId($row['ID_Menu_Nvl_2'] ?? null);
      $lvl3 = $this->normalizeId($row['ID_Menu_Nvl_3'] ?? null);

      if (!isset($result[$lvl1])) {
        $result[$lvl1] = ['cat_id' => $this->toInt($row['cat_lvl1_id'] ?? null), 'children' => []];
      }
      if (!isset($result[$lvl1]['children'][$lvl2])) {
        $result[$lvl1]['children'][$lvl2] = ['cat_id' => $this->toInt($row['cat_lvl2_id'] ?? null), 'children' => []];
      }
      if (!isset($result[$lvl1]['children'][$lvl2]['children'][$lvl3])) {
        $result[$lvl1]['children'][$lvl2]['children'][$lvl3] = ['cat_id' => $this->toInt($row['cat_lvl3_id'] ?? null), 'children' => []];
      }
    }
    return $result;
  }

  /**
   * @param array<string,mixed> $data
   * @return array<string,array{cat_id:int|null,children:array}>
   */
  private function sanitizeNestedMap(array $data): array {
    $result = [];
    foreach ($data as $key => $node) {
      if (!is_array($node)) {
        continue;
      }
      $catId    = $this->toInt($node['cat_id'] ?? null);
      $children = [];
      if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $childKey => $childNode) {
          $children[$this->normalizeId($childKey)] = $this->sanitizeNestedMap([$childKey => $childNode])[$this->normalizeId($childKey)];
        }
      }
      $result[$this->normalizeId($key)] = [
        'cat_id'   => $catId,
        'children' => $children,
      ];
    }
    return $result;
  }

  /**
   * @param array<string,array{cat_id:int|null,children:array}> $map
   * @param array{lvl1:string,lvl2:string,lvl3:string} $ids
   * @return array{
   *   cat_lvl1_id:int|null,
   *   cat_lvl2_id:int|null,
   *   cat_lvl3_id:int|null,
   *   status:string,
   *   reason:?string,
   *   missing_lvl1:bool,
   *   missing_lvl2:bool,
   *   missing_lvl3:bool
   * }
   */
  private function resolveCategories(array $map, array $ids): array {
    $status       = 'ok';
    $reasons      = [];
    $cat1         = null;
    $cat2         = null;
    $cat3         = null;
    $missingLvl1  = false;
    $missingLvl2  = false;
    $missingLvl3  = false;

    if ($ids['lvl1'] === '') {
      $status      = 'error';
      $reasons[]   = 'missing_id_menu_lvl1';
      $missingLvl1 = true;
      return [
        'cat_lvl1_id' => null,
        'cat_lvl2_id' => null,
        'cat_lvl3_id' => null,
        'status'      => $status,
        'reason'      => implode(';', $reasons),
        'missing_lvl1'=> $missingLvl1,
        'missing_lvl2'=> $missingLvl2,
        'missing_lvl3'=> $missingLvl3,
      ];
    }

    if (!isset($map[$ids['lvl1']])) {
      $status      = 'error';
      $reasons[]   = 'mapping_not_found_lvl1';
      $missingLvl1 = true;
    } else {
      $cat1 = $map[$ids['lvl1']]['cat_id'];
      if ($cat1 === null) {
        $status      = 'warn';
        $reasons[]   = 'cat_lvl1_id_missing';
        $missingLvl1 = true;
      }

      if ($ids['lvl2'] !== '') {
        if (isset($map[$ids['lvl1']]['children'][$ids['lvl2']])) {
          $cat2 = $map[$ids['lvl1']]['children'][$ids['lvl2']]['cat_id'];
          if ($cat2 === null) {
            $status      = $status === 'error' ? 'error' : 'warn';
            $reasons[]   = 'cat_lvl2_id_missing';
            $missingLvl2 = true;
          }

          if ($ids['lvl3'] !== '') {
            if (isset($map[$ids['lvl1']]['children'][$ids['lvl2']]['children'][$ids['lvl3']])) {
              $cat3 = $map[$ids['lvl1']]['children'][$ids['lvl2']]['children'][$ids['lvl3']]['cat_id'];
              if ($cat3 === null) {
                $status      = $status === 'error' ? 'error' : 'warn';
                $reasons[]   = 'cat_lvl3_id_missing';
                $missingLvl3 = true;
              }
            } else {
              $status      = $status === 'error' ? 'error' : 'warn';
              $reasons[]   = 'mapping_not_found_lvl3';
              $missingLvl3 = true;
            }
          }
        } else {
          $status      = $status === 'error' ? 'error' : 'warn';
          $reasons[]   = 'mapping_not_found_lvl2';
          $missingLvl2 = true;
          if ($ids['lvl3'] !== '') {
            $missingLvl3 = true;
          }
        }
      } elseif ($ids['lvl3'] !== '') {
        $status      = $status === 'error' ? 'error' : 'warn';
        $reasons[]   = 'id_menu_lvl2_missing';
        $missingLvl2 = true;
        $missingLvl3 = true;
      }
    }

    return [
      'cat_lvl1_id'  => $cat1,
      'cat_lvl2_id'  => $cat2,
      'cat_lvl3_id'  => $cat3,
      'status'       => $status,
      'reason'       => $reasons ? implode(';', array_unique($reasons)) : null,
      'missing_lvl1' => $missingLvl1,
      'missing_lvl2' => $missingLvl2,
      'missing_lvl3' => $missingLvl3,
    ];
  }

  private function normalizeId($value): string {
    if ($value === null) {
      return '';
    }
    $value = trim((string) $value);
    if ($value === '' || $value === '---' || $value === 'N/A') {
      return '';
    }
    return $value;
  }

  private function toInt($value): ?int {
    if ($value === null || $value === '' || $value === 'null') {
      return null;
    }
    if (is_int($value)) {
      return $value;
    }
    if (is_numeric($value)) {
      return (int) $value;
    }
    if (is_string($value)) {
      $filtered = preg_replace('/[^0-9\-]/', '', $value);
      if ($filtered === '' || $filtered === '-' || $filtered === null) {
        return null;
      }
      return (int) $filtered;
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

  private function toFloat($value): ?float {
    if ($value === null || $value === '') {
      return null;
    }
    if (is_numeric($value)) {
      return (float) $value;
    }
    if (is_string($value)) {
      $clean = preg_replace('/[^0-9.,-]/', '', $value);
      if ($clean === null || $clean === '') {
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
    $lines[] = '# Stage 04 - Resolución de categorías';
    $lines[] = '';
    $lines[] = sprintf('* Archivo de mapeo: `%s`', basename((string) ($metrics['menu_map_path'] ?? 'menu-map.json')));
    $lines[] = sprintf('* Filas procesadas: %d', (int) ($metrics['rows_total'] ?? 0));
    $lines[] = sprintf('* Filas escritas: %d', (int) ($metrics['rows_resolved'] ?? 0));
    if (isset($metrics['status_counts']) && is_array($metrics['status_counts'])) {
      $lines[] = '* Conteo por estado:';
      foreach (['ok', 'warn', 'error'] as $status) {
        $lines[] = sprintf('  * %s: %d', strtoupper($status), (int) ($metrics['status_counts'][$status] ?? 0));
      }
    }
    $lines[] = '';
    $lines[] = 'Los archivos en este directorio corresponden a un subconjunto generado por Stage 04 en modo sandbox.';

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
