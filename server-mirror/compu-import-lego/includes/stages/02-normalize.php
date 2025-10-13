<?php

/** Parseo robusto de CSV sobre línea cruda */
function compu_csv_parse_line($raw) {
  $converted = iconv('UTF-8', 'UTF-8//IGNORE', $raw);
  if ($converted !== false) {
    $raw = $converted;
  }

  $row = str_getcsv($raw, ',', '"', '\\');

  if (is_array($row) && count($row) === 1 && substr_count($raw, ',') >= 3) {
    $raw2 = str_replace('\\"', '""', $raw);
    $row  = str_getcsv($raw2, ',', '"', '\\');
  }

  return $row;
}

/** Limpieza por campo (no sobre la línea completa) */
function compu_field_sanitize($v) {
  if (!is_string($v)) {
    return $v;
  }
  $v = preg_replace('/\s+/u', ' ', trim($v));
  return $v;
}
if (!defined('ABSPATH')) {
  exit;
}

class Compu_Stage_Normalize {
  /**
   * Ejecuta la normalización del CSV dentro del RUN_DIR.
   *
   * @param array<string,mixed> $args
   */
  public function run($args) {
    $runDir   = $this->resolveRunDirectory($args);
    $source   = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'source.csv';
    $jsonPath = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'normalized.jsonl';
    $csvPath  = rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'normalized.csv';

    if (!is_file($source)) {
      $this->cli_error("Falta source.csv en {$runDir}. Ejecuta fetch primero.");
    }

    $handle = fopen($source, 'r');
    if ($handle === false) {
      $this->cli_error('No se pudo abrir source.csv para lectura.');
    }

    $headerLine = fgets($handle);
    if ($headerLine === false) {
      fclose($handle);
      $this->cli_error('El CSV no contiene encabezados.');
    }

    $headerRaw = compu_csv_parse_line($headerLine);
    if (!is_array($headerRaw) || count($headerRaw) === 0) {
      fclose($handle);
      $this->cli_error('El CSV no contiene encabezados válidos.');
    }

    $headerRaw = array_map('compu_field_sanitize', $headerRaw);

    $headerMeta     = $this->buildHeaderMeta($headerRaw);
    $modeloIndex    = $this->findModeloIndex($headerMeta);
    $headerMeta     = $this->ensureSkuColumn($headerMeta, $modeloIndex);
    $normalizedHead = $this->finalizeHeaderNames($headerMeta);
    $headerCount    = count($headerRaw);

    // Codex audit: persistimos el mapa de encabezados para las auditorías 01-03.
    $this->persistHeaderMap(
      rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'header-map.json',
      $headerMeta
    );

    $jsonHandle = fopen($jsonPath, 'w');
    $csvHandle  = fopen($csvPath, 'w');
    if ($jsonHandle === false || $csvHandle === false) {
      if ($handle !== false) {
        fclose($handle);
      }
      if ($jsonHandle !== false) {
        fclose($jsonHandle);
      }
      if ($csvHandle !== false) {
        fclose($csvHandle);
      }
      $this->cli_error('No se pudieron crear los archivos de salida.');
    }

    fputcsv($csvHandle, $normalizedHead, ',', '"', '\\');

    while (($rawLine = fgets($handle)) !== false) {
      $row = compu_csv_parse_line($rawLine);
      if (!is_array($row) || count($row) === 0) {
        continue;
      }

      $row = array_map('compu_field_sanitize', $row);

      if (count($row) > $headerCount) {
        $row = array_slice($row, 0, $headerCount);
      } elseif (count($row) < $headerCount) {
        $row = array_pad($row, $headerCount, '');
      }

      if ($this->isEmptyRow($row)) {
        continue;
      }

      $assocRow = array_combine($headerRaw, $row);
      if ($assocRow === false) {
        continue;
      }

      $assoc = [];
      $csvRow = [];
      $modeloValue = $modeloIndex !== null ? $this->valueFromRow($row, $modeloIndex) : '';

      foreach ($headerMeta as $position => $meta) {
        $value = $this->valueFromRow($row, $meta['source_index']);

        if ($meta['is_sku']) {
          if ($value === '') {
            $value = $modeloValue;
          }
        }

        $assoc[$meta['normalized']] = $value;
        $csvRow[$position] = $value;
      }

      // [COMPUSTAR][ADD] enrich Nombre y sumas de stock
      if ($this->compu_stage02_flag_enabled('ST2_ENRICH_NAME_STOCK')) {
        $this->compu_stage02_enrich_name_and_stock($assoc);
      }
      // [/COMPUSTAR][ADD]

      fwrite($jsonHandle, $this->encodeJsonLine($assoc));
      fputcsv($csvHandle, $csvRow, ',', '"', '\\');
    }

    fclose($handle);
    fclose($jsonHandle);
    fclose($csvHandle);
  }

  /**
   * @param array<int,array{normalized:string,source_index:int,original:string,is_sku:bool}> $meta
   */
  private function persistHeaderMap(string $path, array $meta): void {
    $map = [];
    foreach ($meta as $item) {
      $map[] = [
        'original'      => $item['original'],
        'normalized'    => $item['normalized'],
        'source_index'  => $item['source_index'],
        'is_sku_column' => $item['is_sku'],
      ];
    }

    $payload = [
      'generated_at' => gmdate('c'),
      'columns'      => $map,
    ];

    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
      $this->cli_error('No se pudo serializar el mapa de encabezados.');
    }

    if (file_put_contents($path, $encoded) === false) {
      $this->cli_error("No se pudo escribir header-map.json en {$path}.");
    }
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
      if ($resolved !== '') {
        return $resolved;
      }
    }

    $this->cli_error('No se indicó el RUN_DIR para Stage 02.');
    return '';
  }

  private function detectDelimiter(string $file): string {
    $sample = file_get_contents($file, false, null, 0, 4096);
    if ($sample === false) {
      return ',';
    }
    $candidates = [',', ';', "\t", '|'];
    $best = ',';
    $bestCount = -1;
    foreach ($candidates as $candidate) {
      $count = substr_count($sample, $candidate);
      if ($count > $bestCount) {
        $best = $candidate;
        $bestCount = $count;
      }
    }
    return $best;
  }

  /**
   * @param array<int,string> $headerRaw
   * @return array<int,array{normalized:string,source_index:int,original:string,is_sku:bool}>
   */
  private function buildHeaderMeta(array $headerRaw): array {
    $meta = [];
    foreach ($headerRaw as $index => $label) {
      $meta[] = [
        'normalized'    => $this->normalizeHeader((string) $label),
        'source_index'  => $index,
        'original'      => (string) $label,
        'is_sku'        => $this->isSkuLabel((string) $label),
      ];
    }
    return $meta;
  }

  /**
   * @param array<int,array{normalized:string,source_index:int,original:string,is_sku:bool}> $meta
   */
  private function findModeloIndex(array $meta): ?int {
    foreach ($meta as $item) {
      if ($this->normalizeHeader($item['original']) === 'Modelo') {
        return $item['source_index'];
      }
    }
    return null;
  }

  /**
   * @param array<int,array{normalized:string,source_index:int,original:string,is_sku:bool}> $meta
   * @return array<int,array{normalized:string,source_index:int,original:string,is_sku:bool}>
   */
  private function ensureSkuColumn(array $meta, ?int $modeloIndex): array {
    $hasSku = false;
    foreach ($meta as $item) {
      if ($item['is_sku']) {
        $hasSku = true;
        break;
      }
    }

    if ($hasSku) {
      return $meta;
    }

    $insertPosition = count($meta);
    if ($modeloIndex !== null) {
      foreach ($meta as $idx => $item) {
        if ($item['source_index'] === $modeloIndex) {
          $insertPosition = $idx + 1;
          break;
        }
      }
    }

    $skuMeta = [
      'normalized'   => 'SKU',
      'source_index' => $modeloIndex ?? 0,
      'original'     => 'SKU',
      'is_sku'       => true,
    ];

    array_splice($meta, $insertPosition, 0, [$skuMeta]);
    return $meta;
  }

  /**
   * @param array<int,array{normalized:string,source_index:int,original:string,is_sku:bool}> $meta
   * @return array<int,string>
   */
  private function finalizeHeaderNames(array &$meta): array {
    $normalizedNames = array_map(function ($item) {
      return $item['normalized'];
    }, $meta);

    $unique = $this->ensureUniqueNames($normalizedNames);
    foreach ($meta as $index => &$item) {
      $item['normalized'] = $unique[$index];
      if ($item['is_sku']) {
        $item['normalized'] = 'sku';
      }
    }
    unset($item);

    return array_map(function ($item) {
      return $item['normalized'];
    }, $meta);
  }

  /**
   * @param array<int,string> $headers
   * @return array<int,string>
   */
  private function ensureUniqueNames(array $headers): array {
    $seen = [];
    $result = [];
    foreach ($headers as $header) {
      $base = $header !== '' ? $header : 'Column';
      $candidate = $base;
      $suffix = 2;
      while (isset($seen[$candidate])) {
        $candidate = $base . '_' . $suffix;
        $suffix++;
      }
      $seen[$candidate] = true;
      $result[] = $candidate;
    }
    return $result;
  }

  private function normalizeHeader(string $header): string {
    $header = trim($header);
    $header = $this->removeDiacritics($header);
    $header = preg_replace('/\s+/u', '_', $header);
    $header = preg_replace('/[^A-Za-z0-9_]/u', '_', $header);
    $header = preg_replace('/_+/', '_', $header);
    return trim($header, '_');
  }

  private function removeDiacritics(string $value): string {
    if ($value === '') {
      return $value;
    }
    if (class_exists('Transliterator')) {
      $trans = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC;');
      if ($trans) {
        $value = $trans->transliterate($value);
      }
      return $value;
    }
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($converted !== false) {
      return $converted;
    }
    return $value;
  }

  private function isSkuLabel(string $label): bool {
    return strcasecmp($this->normalizeHeader($label), 'SKU') === 0;
  }

  /**
   * @param array<int,string> $row
   */
  private function isEmptyRow(array $row): bool {
    foreach ($row as $value) {
      if (trim((string) $value) !== '') {
        return false;
      }
    }
    return true;
  }

  /**
   * @param array<int,string> $row
   */
  private function valueFromRow(array $row, int $index): string {
    if (!array_key_exists($index, $row)) {
      return '';
    }
    $value = (string) $row[$index];
    return $this->sanitizeValue($value);
  }

  /**
   * @param array<string,string> $assoc
   */
  private function encodeJsonLine(array $assoc): string {
    $encoded = json_encode($assoc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
      $this->cli_error('No se pudo codificar una fila a JSON.');
    }
    return $encoded . "\n";
  }

  // [COMPUSTAR][ADD] helpers flag y cálculos de stock
  private function compu_stage02_flag_enabled(string $name): bool {
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
   * @param array<string,mixed> $assoc
   */
  private function compu_stage02_enrich_name_and_stock(array &$assoc): void {
    $marca  = isset($assoc['Marca']) ? (string) $assoc['Marca'] : '';
    $modelo = isset($assoc['Modelo']) ? (string) $assoc['Modelo'] : '';
    $titulo = isset($assoc['Titulo']) ? (string) $assoc['Titulo'] : '';

    $nombre = trim($marca . ' ' . $modelo . ' ' . $titulo);
    if ($nombre !== '') {
      $normalized = preg_replace('/\s+/', ' ', $nombre);
      if (is_string($normalized) && $normalized !== '') {
        $nombre = trim($normalized);
      }
    }
    $assoc['Nombre'] = $nombre;

    $branchColumns = [
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

    $total = 0.0;
    $sinTijuana = 0.0;

    foreach ($assoc as $key => $value) {
      $numeric = $this->compu_stage02_parse_stock($value);
      if ($numeric <= 0) {
        continue;
      }

      $keyStr = (string) $key;
      $isBranch = isset($branchColumns[$keyStr]);
      $isStockPrefix = stripos($keyStr, 'Stock_') === 0;
      if (!$isBranch && !$isStockPrefix) {
        continue;
      }

      $isTijuana = (strcasecmp($keyStr, 'Tijuana') === 0 || strcasecmp($keyStr, 'Stock_Tijuana') === 0);

      $total += $numeric;
      if (!$isTijuana) {
        $sinTijuana += $numeric;
      }
    }

    $assoc['Stock_Suma_Sin_Tijuana'] = (int) round($sinTijuana);
    $assoc['Stock_Suma_Total'] = (int) round($total);
  }

  private function compu_stage02_parse_stock($value): float {
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

    if (!is_finite($numeric)) {
      return 0.0;
    }

    return max($numeric, 0.0);
  }
  // [/COMPUSTAR][ADD]

  private function sanitizeValue(string $value): string {
    if ($value === '') {
      return '';
    }

    if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
      return $value;
    }

    $converted = false;
    if (function_exists('mb_convert_encoding')) {
      $converted = @mb_convert_encoding($value, 'UTF-8', 'WINDOWS-1252');
    }
    if (is_string($converted) && $converted !== '') {
      return $converted;
    }

    $iconv = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
    if ($iconv !== false && $iconv !== '') {
      return $iconv;
    }

    return preg_replace('/[\x00-\x1F\x7F]/', '', $value);
  }

  private function cli_error(string $message): void {
    if (class_exists('\\WP_CLI')) {
      \WP_CLI::error($message);
    }
    throw new \RuntimeException($message);
  }
}
