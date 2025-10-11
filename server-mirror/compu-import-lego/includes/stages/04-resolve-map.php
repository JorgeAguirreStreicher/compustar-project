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

    // [COMPUSTAR][ADD] Contexto para márgenes dinámicos
    $compuMarginContext = [
      'enabled' => $this->compu_stage04_flag_enabled('ST4_ENRICH_MARGIN'),
      'lookup'  => [],
      'default' => 0.15,
      'updates' => [],
    ];
    if ($compuMarginContext['enabled']) {
      $compuMarginContext['lookup'] = $this->compu_stage04_load_margin_lookup($logHandle);
      if (empty($compuMarginContext['lookup'])) {
        $this->log($logHandle, 'info', 'ST4_ENRICH_MARGIN activo sin datos de margen; se usará default 0.15.', []);
      }
    }
    // [/COMPUSTAR][ADD]

    // [COMPUSTAR][ADD] Estado para métricas de márgenes vía mapa de categorías
    $compuStage04MarginDbState = [
      'enabled' => isset($compuMarginContext['enabled']) ? (bool) $compuMarginContext['enabled'] : $this->compu_stage04_flag_enabled('ST4_ENRICH_MARGIN'),
      'found' => 0,
      'default' => 0,
    ];
    // [/COMPUSTAR][ADD]

    // [COMPUSTAR][ADD] Contenedor para métricas inline de margen
    $metrics = [];
    // [/COMPUSTAR][ADD]

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

      // [COMPUSTAR][ADD] Estado inicial de margen para la fila actual
      $compuStage04MarginAlreadyPresent = array_key_exists('margin_pct', $decoded);
      $inlineMarginForResolved = null;
      $inlineMarginUsedDefault = false;
      // [/COMPUSTAR][ADD]

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

      // [COMPUSTAR][ADD] margen en línea junto con la categoría
      if ((int) getenv('ST4_ENRICH_MARGIN') !== 0) {
        if (!$compuStage04MarginAlreadyPresent) {
          global $wpdb;

          $termIdForInlineMargin = null;
          if (isset($decoded['woo_term_id'])) {
            $inlineTermCandidate = (int) $decoded['woo_term_id'];
            if ($inlineTermCandidate > 0) {
              $termIdForInlineMargin = $inlineTermCandidate;
            }
          }

          $vendorL3ForInlineMargin = null;
          if (isset($decoded['ID_Menu_Nvl_3'])) {
            $inlineVendorCandidate = (int) $decoded['ID_Menu_Nvl_3'];
            if ($inlineVendorCandidate > 0) {
              $vendorL3ForInlineMargin = $inlineVendorCandidate;
            }
          }

          $inlineMarginValue = 0.1500;
          if (isset($wpdb) && is_object($wpdb)) {
            $inlineMarginValue = compu_inline_get_margin_pct($wpdb, $termIdForInlineMargin, $vendorL3ForInlineMargin);
          }

          $decoded['margin_pct'] = number_format($inlineMarginValue, 4, '.', '');
          $inlineMarginForResolved = $decoded['margin_pct'];
          if ($inlineMarginValue == 0.1500) {
            $inlineMarginUsedDefault = true;
          }

          $metrics['margin_inline_assigned'] = ($metrics['margin_inline_assigned'] ?? 0) + 1;
          if ($inlineMarginValue == 0.1500) {
            $metrics['margin_inline_default'] = ($metrics['margin_inline_default'] ?? 0) + 1;
          }
        }
      }
      // [/COMPUSTAR][ADD]

      // [COMPUSTAR][ADD] Aplicar margen por SKU
      if ($compuMarginContext['enabled']) {
        $skuValue = $this->stringValue($decoded['SKU'] ?? ($decoded['sku'] ?? ''));
        $marginData = $this->compu_stage04_resolve_margin($skuValue, $compuMarginContext['lookup'], $compuMarginContext['default']);
        $decoded['margin_pct'] = $marginData['margin'];
        if ($marginData['default']) {
          $decoded['margin_default'] = true;
        }
        if ($skuValue !== '') {
          $upperSku = strtoupper($skuValue);
          $compuMarginContext['updates'][$upperSku] = ['margin_pct' => $marginData['margin']];
          if ($marginData['default']) {
            $compuMarginContext['updates'][$upperSku]['margin_default'] = true;
          }
        }
      }
      // [/COMPUSTAR][ADD]

      // [COMPUSTAR][ADD] Resolver margen desde mapa de categorías con prioridades term/vendor
      if ($compuStage04MarginDbState['enabled']) {
        $termId = null;
        if (isset($decoded['woo_term_id'])) {
          $termCandidate = (int) $decoded['woo_term_id'];
          if ($termCandidate > 0) {
            $termId = $termCandidate;
          }
        }

        $vendorL3Id = null;
        if (isset($decoded['ID_Menu_Nvl_3'])) {
          $vendorCandidate = (int) $decoded['ID_Menu_Nvl_3'];
          if ($vendorCandidate > 0) {
            $vendorL3Id = $vendorCandidate;
          }
        }

        $marginUsedDefault = false;
        $marginFromMap = $this->compu_stage04_get_margin_pct($termId, $vendorL3Id, $marginUsedDefault);
        $decoded['margin_pct'] = $this->compu_stage04_format_margin_pct($marginFromMap);

        if ($marginUsedDefault) {
          $decoded['margin_default'] = true;
          $compuStage04MarginDbState['default']++;
        } else {
          if (isset($decoded['margin_default'])) {
            unset($decoded['margin_default']);
          }
          $compuStage04MarginDbState['found']++;
        }

        $skuForMargin = $this->stringValue($decoded['SKU'] ?? ($decoded['sku'] ?? ''));
        if ($skuForMargin !== '' && isset($compuMarginContext) && is_array($compuMarginContext) && !empty($compuMarginContext['enabled'])) {
          $upperSku = strtoupper($skuForMargin);
          if (!isset($compuMarginContext['updates'][$upperSku]) || !is_array($compuMarginContext['updates'][$upperSku])) {
            $compuMarginContext['updates'][$upperSku] = [];
          }
          $compuMarginContext['updates'][$upperSku]['margin_pct'] = $decoded['margin_pct'];
          if ($marginUsedDefault) {
            $compuMarginContext['updates'][$upperSku]['margin_default'] = true;
          } elseif (isset($compuMarginContext['updates'][$upperSku]['margin_default'])) {
            unset($compuMarginContext['updates'][$upperSku]['margin_default']);
          }
        }
      }
      // [/COMPUSTAR][ADD]

      // [COMPUSTAR][ADD] Mantener el margen calculado en línea antes de escribir la fila
      if ($inlineMarginForResolved !== null) {
        $decoded['margin_pct'] = $inlineMarginForResolved;
        if ($inlineMarginUsedDefault && !isset($decoded['margin_default'])) {
          $decoded['margin_default'] = true;
        } elseif (!$inlineMarginUsedDefault && isset($decoded['margin_default'])) {
          unset($decoded['margin_default']);
        }

        if (isset($compuMarginContext) && is_array($compuMarginContext) && !empty($compuMarginContext['enabled'])) {
          $skuInlinePersist = $this->stringValue($decoded['SKU'] ?? ($decoded['sku'] ?? ''));
          if ($skuInlinePersist !== '') {
            $upperInlinePersist = strtoupper($skuInlinePersist);
            if (!isset($compuMarginContext['updates'][$upperInlinePersist]) || !is_array($compuMarginContext['updates'][$upperInlinePersist])) {
              $compuMarginContext['updates'][$upperInlinePersist] = [];
            }
            $compuMarginContext['updates'][$upperInlinePersist]['margin_pct'] = $decoded['margin_pct'];
            if ($inlineMarginUsedDefault) {
              $compuMarginContext['updates'][$upperInlinePersist]['margin_default'] = true;
            } elseif (isset($compuMarginContext['updates'][$upperInlinePersist]['margin_default'])) {
              unset($compuMarginContext['updates'][$upperInlinePersist]['margin_default']);
            }
          }
        }
      }
      // [/COMPUSTAR][ADD]

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

    // [COMPUSTAR][ADD] Preservar métricas de margen inline para resumen y métricas
    $compuInlineMarginMetrics = $metrics;
    // [/COMPUSTAR][ADD]

    // [COMPUSTAR][ADD] Fusionar margen en validated.jsonl
    if ($compuMarginContext['enabled'] && !empty($compuMarginContext['updates'])) {
      $this->compu_stage04_merge_margin_into_validated($validated, $compuMarginContext['updates'], $logHandle);
    }
    // [/COMPUSTAR][ADD]

    $summary = [
      'rows_in'       => $rowsIn,
      'rows_out'      => $rowsOut,
      'blocked_lvl1'  => $blockedLvl1,
      'status_counts' => $statusCounts,
      'log_path'      => $logPath,
      'resolved_path' => $resolved,
    ];

    // [COMPUSTAR][ADD] Métricas de margen en el resumen del Stage 04
    if (isset($compuStage04MarginDbState) && !empty($compuStage04MarginDbState['enabled'])) {
      $summary['margin_found'] = (int) $compuStage04MarginDbState['found'];
      $summary['margin_default'] = (int) $compuStage04MarginDbState['default'];
    }
    // [/COMPUSTAR][ADD]

    // [COMPUSTAR][ADD] Resumen con métricas de margen inline
    if (isset($compuInlineMarginMetrics['margin_inline_assigned'])) {
      $summary['margin_inline_assigned'] = (int) $compuInlineMarginMetrics['margin_inline_assigned'];
    }
    if (isset($compuInlineMarginMetrics['margin_inline_default'])) {
      $summary['margin_inline_default'] = (int) $compuInlineMarginMetrics['margin_inline_default'];
    }
    // [/COMPUSTAR][ADD]

    $this->log($logHandle, 'info', 'Resumen Stage 04', $summary);
    fclose($logHandle);

    $metrics = array_merge($summary, [
      'generated_at' => gmdate('c'),
    ]);

    // [COMPUSTAR][ADD] Propagar métricas de margen a stage-04.metrics.json
    if (isset($compuStage04MarginDbState) && !empty($compuStage04MarginDbState['enabled'])) {
      $metrics['margin_found'] = (int) $compuStage04MarginDbState['found'];
      $metrics['margin_default'] = (int) $compuStage04MarginDbState['default'];
    }
    // [/COMPUSTAR][ADD]

    // [COMPUSTAR][ADD] Métricas inline en stage-04.metrics.json
    if (isset($compuInlineMarginMetrics['margin_inline_assigned'])) {
      $metrics['margin_inline_assigned'] = (int) $compuInlineMarginMetrics['margin_inline_assigned'];
    }
    if (isset($compuInlineMarginMetrics['margin_inline_default'])) {
      $metrics['margin_inline_default'] = (int) $compuInlineMarginMetrics['margin_inline_default'];
    }
    // [/COMPUSTAR][ADD]

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

  // [COMPUSTAR][ADD] helpers de margen y merge validated
  private function compu_stage04_flag_enabled(string $name): bool {
    $raw = getenv($name);
    if ($raw === false || $raw === '') {
      return true;
    }
    $normalized = strtolower(trim((string) $raw));
    if ($normalized === '') {
      return true;
    }
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
  }

  /**
   * @param resource $logHandle
   * @return array<string,float>
   */
  private function compu_stage04_load_margin_lookup($logHandle): array {
    $lookup = [];

    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb)) {
      $this->log($logHandle, 'warning', 'wpdb no disponible; se usará margen por defecto.', []);
      return $lookup;
    }

    $prefix = property_exists($wpdb, 'prefix') ? (string) $wpdb->prefix : '';
    $candidates = [];
    $candidates[] = 'wp_compu_cats_map';
    if ($prefix !== '' && $prefix !== 'wp_') {
      $candidates[] = $prefix . 'compu_cats_map';
    }
    $candidates[] = 'compu_cats_map';
    if ($prefix !== '' && $prefix !== 'wp_') {
      $candidates[] = $prefix . 'compu_cats_map';
    }

    $seen = [];
    foreach ($candidates as $candidate) {
      if ($candidate === '' || isset($seen[$candidate])) {
        continue;
      }
      $seen[$candidate] = true;

      try {
        $rows = $wpdb->get_results("SELECT * FROM {$candidate} LIMIT 1000", ARRAY_A);
      } catch (\Throwable $th) {
        $this->log($logHandle, 'warning', 'Error consultando tabla de márgenes.', [
          'table' => $candidate,
          'error' => $th->getMessage(),
        ]);
        continue;
      }

      if (!is_array($rows) || empty($rows)) {
        continue;
      }

      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $sku = $this->compu_stage04_extract_sku($row);
        $margin = $this->compu_stage04_extract_margin($row);
        if ($sku === '' || $margin === null) {
          continue;
        }
        $lookup[strtoupper($sku)] = $this->compu_stage04_normalize_margin($margin);
      }

      if (!empty($lookup)) {
        break;
      }
    }

    return $lookup;
  }

  /**
   * @param array<string,mixed> $row
   */
  private function compu_stage04_extract_sku(array $row): string {
    foreach ($row as $key => $value) {
      $lower = strtolower((string) $key);
      if (strpos($lower, 'sku') === false) {
        continue;
      }
      $stringValue = $this->stringValue($value ?? '');
      if ($stringValue !== '') {
        return $stringValue;
      }
    }
    return '';
  }

  /**
   * @param array<string,mixed> $row
   */
  private function compu_stage04_extract_margin(array $row): ?float {
    foreach ($row as $key => $value) {
      $lower = strtolower((string) $key);
      if (strpos($lower, 'margin') === false) {
        continue;
      }
      $numeric = $this->compu_stage04_to_float($value);
      if ($numeric === null) {
        continue;
      }
      if ($numeric > 0 && $numeric <= 1) {
        return $numeric;
      }
    }
    return null;
  }

  /**
   * @return array{margin:float,default:bool}
   */
  private function compu_stage04_resolve_margin(string $sku, array $lookup, float $default): array {
    $margin = $default;
    $usedDefault = true;
    if ($sku !== '') {
      $key = strtoupper($sku);
      if (isset($lookup[$key])) {
        $margin = $lookup[$key];
        $usedDefault = false;
      }
    }
    return [
      'margin' => $this->compu_stage04_normalize_margin($margin),
      'default' => $usedDefault,
    ];
  }

  private function compu_stage04_normalize_margin($value): float {
    $numeric = $this->compu_stage04_to_float($value);
    if ($numeric === null) {
      return 0.15;
    }
    if ($numeric < 0) {
      $numeric = 0.0;
    }
    return (float) sprintf('%.4f', $numeric);
  }

  private function compu_stage04_to_float($value): ?float {
    if ($value === null || $value === '') {
      return null;
    }
    if (is_float($value) || is_int($value)) {
      return (float) $value;
    }
    if (is_string($value)) {
      $clean = preg_replace('/[^0-9.,-]/', '', $value);
      if ($clean === null || $clean === '' || $clean === '-') {
        return null;
      }
      $clean = str_replace(',', '', $clean);
      if ($clean === '' || $clean === '-' || !is_numeric($clean)) {
        return null;
      }
      return (float) $clean;
    }
    return null;
  }

  /**
   * @param array<string,array<string,mixed>> $updates
   * @param resource $logHandle
   */
  private function compu_stage04_merge_margin_into_validated(string $path, array $updates, $logHandle): void {
    if (!is_file($path)) {
      $this->log($logHandle, 'warning', 'validated.jsonl no existe; no se pudo fusionar margin_pct.', [
        'path' => $path,
      ]);
      return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
      $this->log($logHandle, 'warning', 'No se pudo leer validated.jsonl para fusionar márgenes.', [
        'path' => $path,
      ]);
      return;
    }

    $updatedLines = [];
    foreach ($lines as $line) {
      $trimmed = trim((string) $line);
      if ($trimmed === '') {
        $updatedLines[] = $line;
        continue;
      }
      $decoded = json_decode($trimmed, true);
      if (!is_array($decoded)) {
        $updatedLines[] = $line;
        continue;
      }
      $skuValue = $this->stringValue($decoded['SKU'] ?? ($decoded['sku'] ?? ''));
      if ($skuValue !== '') {
        $upperSku = strtoupper($skuValue);
        if (isset($updates[$upperSku])) {
          foreach ($updates[$upperSku] as $field => $value) {
            if ($field === 'margin_pct') {
              $decoded[$field] = $this->compu_stage04_normalize_margin($value);
            } elseif ($field === 'margin_default') {
              $decoded[$field] = (bool) $value;
            } else {
              $decoded[$field] = $value;
            }
          }
        }
      }

      $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($encoded === false) {
        $updatedLines[] = $line;
        continue;
      }
      $updatedLines[] = $encoded;
    }

    file_put_contents($path, implode("\n", $updatedLines) . "\n");
  }
  // [/COMPUSTAR][ADD]

  // [COMPUSTAR][ADD] Resolución de márgenes desde wp_compu_cats_map / compu_cats_map
  private function compu_stage04_get_margin_pct(?int $termId, ?int $vendorL3Id, ?bool &$usedDefault = null): float {
    static $cacheByTerm   = [];
    static $cacheByVendor = [];

    $DEFAULT_MARGIN = 0.1500;
    $usedDefault    = false;

    $termKey   = ($termId !== null && $termId > 0) ? (int) $termId : null;
    $vendorKey = ($vendorL3Id !== null && $vendorL3Id > 0) ? (int) $vendorL3Id : null;

    if ($termKey !== null && isset($cacheByTerm[$termKey])) {
      $cached = $cacheByTerm[$termKey];
      $usedDefault = (bool) ($cached['default'] ?? false);
      return (float) ($cached['value'] ?? $DEFAULT_MARGIN);
    }

    if ($vendorKey !== null && isset($cacheByVendor[$vendorKey])) {
      $cached = $cacheByVendor[$vendorKey];
      $usedDefault = (bool) ($cached['default'] ?? false);
      return (float) ($cached['value'] ?? $DEFAULT_MARGIN);
    }

    $marginValue = null;

    $tablesToCheck = $this->compu_stage04_margin_table_sequence();
    $visitedTables = [];

    foreach ($tablesToCheck as $tableName) {
      $tableName = trim((string) $tableName);
      if ($tableName === '' || isset($visitedTables[$tableName])) {
        continue;
      }
      $visitedTables[$tableName] = true;

      if ($termKey !== null) {
        $marginValue = $this->compu_stage04_fetch_margin_value($tableName, 'term_id', $termKey);
      }

      if ($marginValue !== null) {
        break;
      }

      if ($vendorKey !== null) {
        $marginValue = $this->compu_stage04_fetch_margin_value($tableName, 'vendor_l3_id', $vendorKey);
      }

      if ($marginValue !== null) {
        break;
      }
    }

    if ($marginValue === null) {
      $usedDefault = true;
      $marginValue = $DEFAULT_MARGIN;
    }

    $normalizedMargin = $this->compu_stage04_normalize_margin($marginValue);

    $cachePayload = [
      'value'   => $normalizedMargin,
      'default' => $usedDefault,
    ];

    if ($termKey !== null) {
      $cacheByTerm[$termKey] = $cachePayload;
    }

    if ($vendorKey !== null) {
      $cacheByVendor[$vendorKey] = $cachePayload;
    }

    return (float) $normalizedMargin;
  }

  /**
   * @return array<int,string>
   */
  private function compu_stage04_margin_table_sequence(): array {
    $tables = ['wp_compu_cats_map'];

    global $wpdb;
    if (isset($wpdb) && is_object($wpdb) && property_exists($wpdb, 'prefix')) {
      $prefix = (string) $wpdb->prefix;
      if ($prefix !== '') {
        $prefixedTable = $prefix . 'compu_cats_map';
        if (!in_array($prefixedTable, $tables, true)) {
          $tables[] = $prefixedTable;
        }
      }
    }

    if (!in_array('compu_cats_map', $tables, true)) {
      $tables[] = 'compu_cats_map';
    }

    return $tables;
  }

  private function compu_stage04_fetch_margin_value(string $table, string $column, int $value): ?float {
    global $wpdb;

    if (!isset($wpdb) || !is_object($wpdb)) {
      return null;
    }

    $table = trim($table);
    $column = trim($column);

    if ($table === '' || $column === '') {
      return null;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
      return null;
    }

    $sql = sprintf('SELECT margin_pct FROM `%s` WHERE `%s` = %%d LIMIT 1', $table, $column);
    $prepared = $wpdb->prepare($sql, $value);
    if ($prepared === false) {
      return null;
    }

    try {
      $result = $wpdb->get_var($prepared);
    } catch (\Throwable $th) {
      return null;
    }

    if ($result === null) {
      return null;
    }

    $numeric = $this->compu_stage04_to_float($result);
    if ($numeric === null) {
      return null;
    }

    if ($numeric < 0) {
      $numeric = 0.0;
    } elseif ($numeric > 1) {
      $numeric = 1.0;
    }

    return (float) $numeric;
  }

  private function compu_stage04_format_margin_pct(float $value): string {
    return number_format(max(0.0, min(1.0, $value)), 4, '.', '');
  }
  // [/COMPUSTAR][ADD]

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

// [COMPUSTAR][ADD] resolver margen con cache + fallbacks (lee 'margin' o 'margin_pct')
function compu_inline_get_margin_pct($wpdb, $term_id = null, $vendor_l3_id = null) {
  static $cache_term = [];
  static $cache_l3   = [];
  $DEFAULT = 0.1500;

  if ($term_id && isset($cache_term[$term_id])) return $cache_term[$term_id];
  if ($vendor_l3_id && isset($cache_l3[$vendor_l3_id])) return $cache_l3[$vendor_l3_id];

  $m = null;

  // Vista preferida
  if ($term_id) {
    $sql = "SELECT COALESCE(margin, margin_pct) AS m FROM wp_compu_cats_map WHERE term_id = %d LIMIT 1";
    $m = $wpdb->get_var($wpdb->prepare($sql, $term_id));
  }
  if ($m === null && $vendor_l3_id) {
    $sql = "SELECT COALESCE(margin, margin_pct) AS m FROM wp_compu_cats_map WHERE vendor_l3_id = %d LIMIT 1";
    $m = $wpdb->get_var($wpdb->prepare($sql, $vendor_l3_id));
  }

  // Fallback a tabla
  if ($m === null && $term_id) {
    $sql = "SELECT margin_pct AS m FROM compu_cats_map WHERE term_id = %d LIMIT 1";
    $m = $wpdb->get_var($wpdb->prepare($sql, $term_id));
  }
  if ($m === null && $vendor_l3_id) {
    $sql = "SELECT margin_pct AS m FROM compu_cats_map WHERE vendor_l3_id = %d LIMIT 1";
    $m = $wpdb->get_var($wpdb->prepare($sql, $vendor_l3_id));
  }

  $m = ($m === null) ? $DEFAULT : max(0.0, min(1.0, (float) $m));
  if ($term_id)      $cache_term[$term_id]     = $m;
  if ($vendor_l3_id) $cache_l3[$vendor_l3_id]  = $m;
  return $m;
}
// [/COMPUSTAR][ADD]
