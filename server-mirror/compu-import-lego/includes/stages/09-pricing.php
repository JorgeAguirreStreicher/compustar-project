<?php
if (!defined('COMP_RUN_STAGE')) { return; }

require_once dirname(__DIR__) . '/helpers/helpers-common.php';

if (!function_exists('SLOG09')) {
  function SLOG09(string $message): void {
    global $COMP_STAGE09_LOG_HANDLE, $COMP_STAGE09_DEBUG;
    $line = '[' . date('Y-m-d H:i:s') . "] {$message}\n";
    if (is_resource($COMP_STAGE09_LOG_HANDLE)) {
      fwrite($COMP_STAGE09_LOG_HANDLE, $line);
    }
    if (!empty($COMP_STAGE09_DEBUG)) {
      echo $line;
    }
  }
}

if (!function_exists('compu_stage09_current_price')) {
  /** @param WC_Product $product */
  function compu_stage09_current_price($product): float {
    $regular = (float) $product->get_regular_price();
    $sale    = $product->get_sale_price();
    if ($sale !== '') {
      $saleVal = (float) $sale;
      if ($saleVal > 0 && $saleVal < $regular) {
        return $saleVal;
      }
    }
    return $regular;
  }
}

if (!function_exists('compu_stage09_round_margin')) {
  function compu_stage09_round_margin(float $price): float {
    $p = (int) floor($price);
    $last = $p % 10;
    if ($last >= 9) {
      $p -= $last - 9;
    } elseif ($last >= 5) {
      $p -= $last - 5;
    } else {
      $p -= $last;
    }
    return (float) max($p, 0);
  }
}

// [COMPUSTAR][ADD] round_down_059
if (!function_exists('compu_round_down_059')) {
  function compu_round_down_059($x) {
    $i = (int) floor($x);
    $d = $i % 10;
    if ($d >= 9) {
      $i -= ($d - 9);
    } elseif ($d >= 5) {
      $i -= ($d - 5);
    } else {
      $i -= $d;
    }
    return max($i, 0);
  }
}
// [/COMPUSTAR][ADD]

if (!function_exists('compu_stage09_normalize_bool')) {
  /** @param mixed $value */
  function compu_stage09_normalize_bool($value): ?bool {
    if (is_bool($value)) {
      return $value;
    }
    if (is_int($value)) {
      return $value !== 0;
    }
    if (is_string($value)) {
      $normalized = strtolower(trim($value));
      if ($normalized === '') {
        return null;
      }
      if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
        return true;
      }
      if (in_array($normalized, ['0', 'false', 'no', 'n', 'off'], true)) {
        return false;
      }
    }
    return null;
  }
}

// [COMPUSTAR][ADD] helpers para enriquecimiento de precios
if (!function_exists('compu_stage09_flag_enabled')) {
  function compu_stage09_flag_enabled(string $name): bool {
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
}

if (!function_exists('compu_stage09_to_float')) {
  function compu_stage09_to_float($value): ?float {
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
}

if (!function_exists('compu_stage09_extract_sku')) {
  /** @param array<string,mixed> $row */
  function compu_stage09_extract_sku(array $row): string {
    if (isset($row['SKU'])) {
      $value = (string) $row['SKU'];
      if ($value !== '') {
        return trim($value);
      }
    }
    if (isset($row['sku'])) {
      $value = (string) $row['sku'];
      if ($value !== '') {
        return trim($value);
      }
    }
    foreach ($row as $key => $value) {
      $lower = strtolower((string) $key);
      if (strpos($lower, 'sku') === false) {
        continue;
      }
      $stringValue = trim((string) $value);
      if ($stringValue !== '') {
        return $stringValue;
      }
    }
    return '';
  }
}

if (!function_exists('compu_stage09_load_margin_lookup')) {
  function compu_stage09_load_margin_lookup(string $resolvedPath, string $validatedPath): array {
    $lookup = [];
    foreach ([$resolvedPath, $validatedPath] as $path) {
      if ($path === '' || !is_file($path)) {
        continue;
      }
      $lines = file($path, FILE_IGNORE_NEW_LINES);
      if ($lines === false) {
        continue;
      }
      foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
          continue;
        }
        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
          continue;
        }
        if (!array_key_exists('margin_pct', $decoded)) {
          continue;
        }
        $margin = compu_stage09_to_float($decoded['margin_pct']);
        if ($margin === null) {
          continue;
        }
        if ($margin < 0) {
          $margin = 0.0;
        }
        $sku = compu_stage09_extract_sku($decoded);
        if ($sku === '') {
          continue;
        }
        $lookup[strtoupper($sku)] = (float) sprintf('%.4f', $margin);
      }
      if (!empty($lookup)) {
        break;
      }
    }
    return $lookup;
  }
}

if (!function_exists('compu_stage09_build_price_update')) {
  function compu_stage09_build_price_update(string $sku, $usdRaw, $fxRaw, array $marginLookup, float $defaultMargin): array {
    $usd = compu_stage09_to_float($usdRaw);
    $fx  = compu_stage09_to_float($fxRaw);
    $margin = $defaultMargin;
    if ($sku !== '') {
      $key = strtoupper($sku);
      if (isset($marginLookup[$key])) {
        $margin = (float) $marginLookup[$key];
      }
    }
    if ($margin < 0) {
      $margin = 0.0;
    }

    $result = [
      'price_mxn_iva16_rounded' => 0,
      'price_mxn_iva8_rounded'  => 0,
    ];

    $invalid = false;
    if ($usd === null || $usd <= 0 || $fx === null || $fx <= 0) {
      $invalid = true;
    } else {
      $baseMxn = $usd * $fx;
      $precioMargen = $baseMxn * (1 + $margin);
      $p16 = $precioMargen * 1.16;
      $p08 = $precioMargen * 1.08;
      $rounded16 = (int) compu_round_down_059($p16);
      $rounded08 = (int) compu_round_down_059($p08);
      $result['price_mxn_iva16_rounded'] = $rounded16;
      $result['price_mxn_iva8_rounded'] = $rounded08;
      if ($rounded16 <= 0 && $rounded08 <= 0) {
        $invalid = true;
      }
    }

    if ($invalid) {
      $result['price_mxn_iva16_rounded'] = 0;
      $result['price_mxn_iva8_rounded'] = 0;
      $result['price_invalid'] = true;
    }

    return $result;
  }
}

if (!function_exists('compu_stage09_merge_price_updates')) {
  function compu_stage09_merge_price_updates(string $path, array $updates): void {
    if ($path === '' || empty($updates) || !is_file($path)) {
      return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
      SLOG09('No se pudo leer archivo para fusionar precios: ' . $path);
      return;
    }
    $output = [];
    foreach ($lines as $line) {
      $trimmed = trim((string) $line);
      if ($trimmed === '') {
        $output[] = $line;
        continue;
      }
      $decoded = json_decode($trimmed, true);
      if (!is_array($decoded)) {
        $output[] = $line;
        continue;
      }
      $sku = compu_stage09_extract_sku($decoded);
      if ($sku !== '') {
        $upper = strtoupper($sku);
        if (isset($updates[$upper])) {
          foreach ($updates[$upper] as $field => $value) {
            $decoded[$field] = $value;
          }
        }
      }
      $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($encoded === false) {
        $output[] = $line;
        continue;
      }
      $output[] = $encoded;
    }
    file_put_contents($path, implode("\n", $output) . "\n");
  }
}
// [/COMPUSTAR][ADD]

if (!function_exists('compu_stage09_run')) {
  /**
   * Ejecuta Stage 09 (Pricing Woo).
   *
   * @param array{
   *   run_dir?:string,
   *   runDir?:string,
   *   dir?:string,
   *   path?:string,
   *   iva?:float|int|string,
   *   dry_run?:bool|int|string,
   *   debug?:bool|int|string
   * } $opts
   *
   * @return array{
   *   status:string,
   *   ok:bool,
   *   run_dir:string,
   *   dry_run:bool,
   *   iva:float,
   *   stats:array{
   *     synced:int,
   *     price_drops:int,
   *     unchanged:int,
   *     errored:int
   *   },
   *   errors:array<int,string>
   * }
   */
  function compu_stage09_run(array $opts = []): array {
    global $wpdb, $COMP_STAGE09_LOG_HANDLE, $COMP_STAGE09_DEBUG;

    $result = [
      'status' => 'error',
      'ok' => false,
      'run_dir' => '',
      'dry_run' => false,
      'iva' => 16.0,
      'stats' => [
        'synced' => 0,
        'price_drops' => 0,
        'unchanged' => 0,
        'errored' => 0,
      ],
      'errors' => [],
    ];

    $debugRaw = $opts['debug'] ?? getenv('DEBUG');
    $COMP_STAGE09_DEBUG = compu_stage09_normalize_bool($debugRaw) ?? ((int) $debugRaw !== 0);
    $COMP_STAGE09_LOG_HANDLE = null;

    $runDir = compu_import_resolve_run_dir($opts);
    $result['run_dir'] = $runDir;
    if ($runDir === '') {
      $result['errors'][] = 'run_dir_missing: No se pudo determinar RUN_DIR.';
      return $result;
    }
    if (!is_dir($runDir)) {
      $result['errors'][] = 'run_dir_not_found: Directorio inexistente (' . $runDir . ').';
      return $result;
    }

    $priceEnrichment = [
      'enabled' => compu_stage09_flag_enabled('ST9_ENRICH_PRICES'),
      'resolved_path' => rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'resolved.jsonl',
      'validated_path' => rtrim($runDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'validated.jsonl',
      'margin_lookup' => [],
      'default_margin' => 0.15,
      'updates' => [],
    ];
    if ($priceEnrichment['enabled']) {
      $priceEnrichment['margin_lookup'] = compu_stage09_load_margin_lookup(
        $priceEnrichment['resolved_path'],
        $priceEnrichment['validated_path']
      );
      if (empty($priceEnrichment['margin_lookup'])) {
        SLOG09('ST9_ENRICH_PRICES activo: usando margen por defecto 0.15 (sin datos en archivos).');
      }
    }

    $logDir = $runDir . '/logs';
    $finalDir = $runDir . '/final';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    if (!is_dir($finalDir)) { @mkdir($finalDir, 0775, true); }

    $logHandle = @fopen($logDir . '/stage09.log', 'a');
    if (is_resource($logHandle)) {
      $COMP_STAGE09_LOG_HANDLE = $logHandle;
    }

    $csvHandles = [];
    $closeResources = function () use (&$csvHandles, &$COMP_STAGE09_LOG_HANDLE, &$COMP_STAGE09_DEBUG, $logHandle): void {
      foreach ($csvHandles as $handle) {
        if (is_resource($handle)) {
          fclose($handle);
        }
      }
      $csvHandles = [];
      if (is_resource($COMP_STAGE09_LOG_HANDLE ?? null)) {
        fclose($COMP_STAGE09_LOG_HANDLE);
      } elseif (is_resource($logHandle)) {
        fclose($logHandle);
      }
      $COMP_STAGE09_LOG_HANDLE = null;
      $COMP_STAGE09_DEBUG = 0;
    };

    try {
      if (!isset($wpdb) || !is_object($wpdb)) {
        $result['errors'][] = 'wpdb_unavailable: Objeto $wpdb no disponible.';
        SLOG09('Abortando: $wpdb no disponible.');
        return $result;
      }

      if (!function_exists('wc_get_product_id_by_sku') || !function_exists('wc_get_product')) {
        $result['errors'][] = 'woocommerce_missing: WooCommerce no está cargado.';
        SLOG09('WooCommerce no cargado; abortando stage 09.');
        return $result;
      }

      $iva = null;
      if (array_key_exists('iva', $opts) && $opts['iva'] !== '' && $opts['iva'] !== null) {
        $iva = (float) $opts['iva'];
      } else {
        $ivaEnv = getenv('IVA');
        if ($ivaEnv === false || $ivaEnv === '') {
          $ivaEnv = getenv('IVA_DEFAULT');
        }
        if ($ivaEnv !== false && $ivaEnv !== '') {
          $iva = (float) $ivaEnv;
        }
      }
      if ($iva === null || !is_finite($iva)) {
        $iva = 16.0;
      }
      $result['iva'] = $iva;

      $dryRun = null;
      if (array_key_exists('dry_run', $opts)) {
        $dryRun = compu_stage09_normalize_bool($opts['dry_run']);
      }
      if ($dryRun === null) {
        $dryEnv = getenv('DRY_RUN');
        if ($dryEnv !== false && $dryEnv !== '') {
          $dryRun = compu_stage09_normalize_bool($dryEnv);
          if ($dryRun === null) {
            $dryRun = ((int) $dryEnv) !== 0;
          }
        }
      }
      if ($dryRun === null) {
        $dryRun = false;
      }
      $dryRun = (bool) $dryRun;
      $result['dry_run'] = $dryRun;

      $table = $wpdb->prefix . 'compu_offers';
      try {
        $offers = $wpdb->get_results(
          "SELECT supplier_sku, cost_usd, exchange_rate, stock_total FROM {$table}",
          ARRAY_A
        );
      } catch (Throwable $th) {
        $result['errors'][] = 'offers_query_failed: ' . $th->getMessage();
        SLOG09('Error consultando compu_offers: ' . $th->getMessage());
        return $result;
      }

      if (empty($offers)) {
        $result['errors'][] = 'offers_empty: compu_offers sin registros.';
        SLOG09('No hay registros en compu_offers.');
        return $result;
      }

      $csvPaths = [
        'sync' => $finalDir . '/woo_synced.csv',
        'drop' => $finalDir . '/price_dropped.csv',
        'keep' => $finalDir . '/unchanged.csv',
        'err'  => $finalDir . '/errors.csv',
      ];
      foreach ($csvPaths as $key => $path) {
        $handle = @fopen($path, 'w');
        if (!is_resource($handle)) {
          $result['errors'][] = 'file_open_failed: ' . basename($path);
          SLOG09('No se pudo abrir archivo: ' . $path);
          return $result;
        }
        $csvHandles[$key] = $handle;
      }

      fputcsv($csvHandles['sync'], ['sku', 'before', 'target', 'action'], ',', '"', '\\');
      fputcsv($csvHandles['drop'], ['sku', 'from', 'to'], ',', '"', '\\');
      fputcsv($csvHandles['keep'], ['sku', 'price'], ',', '"', '\\');
      fputcsv($csvHandles['err'],  ['sku', 'reason'], ',', '"', '\\');

      foreach ($offers as $offer) {
        $sku = $offer['supplier_sku'] ?? null;
        if (!$sku) {
          $result['stats']['errored']++;
          fputcsv($csvHandles['err'], ['', 'missing_sku'], ',', '"', '\\');
          continue;
        }

        $productId = wc_get_product_id_by_sku($sku);
        if (!$productId) {
          $result['stats']['errored']++;
          fputcsv($csvHandles['err'], [$sku, 'product_not_found'], ',', '"', '\\');
          continue;
        }

        $product = wc_get_product($productId);
        if (!$product) {
          $result['stats']['errored']++;
          fputcsv($csvHandles['err'], [$sku, 'wc_get_product_failed'], ',', '"', '\\');
          continue;
        }

        $costUsdRaw = $offer['cost_usd'] ?? 0;
        $fxRaw      = $offer['exchange_rate'] ?? 0;
        $costUsd = (float) $costUsdRaw;
        $fx      = (float) $fxRaw;
        $stock   = (int)   ($offer['stock_total'] ?? 0);
        if ($costUsd <= 0 || $fx <= 0) {
          $fx = max($fx, 1.0);
        }

        $baseMxn = $costUsd * ($fx > 0 ? $fx : 1.0);
        if ($priceEnrichment['enabled']) {
          $skuString = is_string($sku) ? $sku : (string) $sku;
          if ($skuString !== '') {
            $priceEnrichment['updates'][strtoupper($skuString)] = compu_stage09_build_price_update(
              $skuString,
              $costUsdRaw,
              $fxRaw,
              $priceEnrichment['margin_lookup'],
              $priceEnrichment['default_margin']
            );
          }
        }
        $margin  = 0.15; // TODO: enlazar con tabla de márgenes
        $target  = compu_stage09_round_margin($baseMxn * (1 + $margin) * (1 + ($iva / 100)));

        $before = compu_stage09_current_price($product);
        $shouldUpdate = ($before <= 0 || $target < $before);
        $priceDropped = ($before > 0 && $target < $before);

        if (!$dryRun) {
          if ($shouldUpdate) {
            $product->set_regular_price($target);
            $sale = $product->get_sale_price();
            if ($sale === '' || !is_numeric($sale) || floatval($sale) >= $target) {
              $product->set_sale_price('');
            }
          }

          $product->set_manage_stock(true);
          $product->set_stock_quantity(max(0, $stock));
          $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');

          $product->save();
        }

        if ($shouldUpdate) {
          $result['stats']['synced']++;
          $action = $dryRun ? 'dry_run' : 'lower_or_set';
          fputcsv($csvHandles['sync'], [$sku, $before, $target, $action], ',', '"', '\\');
          if ($priceDropped) {
            $result['stats']['price_drops']++;
            fputcsv($csvHandles['drop'], [$sku, $before, $target], ',', '"', '\\');
          }
        } else {
          $result['stats']['unchanged']++;
          fputcsv($csvHandles['keep'], [$sku, $before], ',', '"', '\\');
        }
      }

      if ($priceEnrichment['enabled'] && !empty($priceEnrichment['updates'])) {
        compu_stage09_merge_price_updates($priceEnrichment['resolved_path'], $priceEnrichment['updates']);
        compu_stage09_merge_price_updates($priceEnrichment['validated_path'], $priceEnrichment['updates']);
      }

      $result['status'] = 'ok';
      $result['ok'] = true;
      SLOG09(
        sprintf(
          'Pricing: synced=%d drops=%d unchanged=%d errors=%d%s',
          $result['stats']['synced'],
          $result['stats']['price_drops'],
          $result['stats']['unchanged'],
          $result['stats']['errored'],
          $dryRun ? ' (dry-run)' : ''
        )
      );

      return $result;
    } finally {
      $closeResources();
    }
  }
}
