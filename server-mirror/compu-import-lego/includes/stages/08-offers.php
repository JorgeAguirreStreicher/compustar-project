<?php
if (!defined('COMP_RUN_STAGE')) { return; }
if (!((defined('WP_CLI') && WP_CLI) || (defined('COMP_RUN_STAGE') && COMP_RUN_STAGE))) { return; }

require_once dirname(__DIR__) . '/helpers/helpers-common.php';
require_once dirname(__DIR__) . '/helpers/helpers-db.php';

/**
 * Stage 08: Offers — persiste inventarios/costos canónicos en wp_compu_offers.
 */
global $wpdb;

$RUN_DIR = compu_import_resolve_run_dir();
$DEBUG   = getenv('DEBUG') ?: 0;
$DRY     = (int) (getenv('DRY_RUN') ?: 0);
$SOURCE  = getenv('OFFERS_SOURCE') ?: 'syscom';
if (!$RUN_DIR) { fwrite(STDERR, "[08] Falta RUN_DIR\n"); return; }
@mkdir("$RUN_DIR/logs", 0775, true);
@mkdir("$RUN_DIR/final", 0775, true);

$LOG = fopen("$RUN_DIR/logs/stage08.log", "a");
function SLOG08($m){ global $LOG,$DEBUG; $l="[".date('Y-m-d H:i:s')."] $m\n"; if($LOG)fwrite($LOG,$l); if($DEBUG)echo $l; }

$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
$rows = $src ? compu_import_read_jsonl($src) : [];
if (empty($rows)) { SLOG08("No hay datos en resolved/validated"); return; }

$csv = fopen("$RUN_DIR/final/offers_upserted.csv","w");
fputcsv($csv,['sku','stock_total','cost_usd','exchange_rate','action'], ',', '"', '\\');

$inserted=0; $updated=0; $unchanged=0; $errors=0;

foreach ($rows as $row) {
  if (!is_array($row)) { continue; }
  $sku = stage08_first($row, ['sku','supplier_sku','model','codigo','code']);
  if (!$sku) {
    $errors++; fputcsv($csv,['',0,0,0,'error']); SLOG08('ROW sin sku');
    continue;
  }

  $offer = stage08_build_offer_payload($row);
  $offer['supplier'] = $offer['supplier'] ?? 'default';

  $product_id = 0;
  if (function_exists('wc_get_product_id_by_sku')) {
    $product_id = (int) wc_get_product_id_by_sku($sku);
  }
  if ($product_id > 0) {
    $offer['product_id'] = $product_id;
  } else {
    $offer['product_id'] = null;
  }

  $action = 'dry_run';
  if ($DRY) {
    $action = 'dry_run';
  } else {
    try {
      $existing = $wpdb->get_row(
        $wpdb->prepare("SELECT id, cost_usd, exchange_rate, stock_total, stock_main, stock_tijuana, stock_by_branch_json, product_id FROM {$wpdb->prefix}compu_offers WHERE supplier_sku=%s AND source=%s", $sku, $SOURCE),
        ARRAY_A
      );
    } catch (\Exception $e) {
      $existing = null;
    }

    $jsonBranches = $offer['stock_by_branch_json'] ?? null;
    if (is_array($jsonBranches)) {
      $offer['stock_by_branch_json'] = wp_json_encode($jsonBranches, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    $changed = true;
    if ($existing) {
      $changed = stage08_has_changed($existing, $offer);
    }

    if (!$changed) {
      $unchanged++;
      $action = 'unchanged';
    } else {
      if (!empty($jsonBranches) && is_array($jsonBranches)) {
        $offer['stock_by_branch_json'] = wp_json_encode($jsonBranches, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      }
      $id = compu_offers_upsert($sku, $SOURCE, $offer);
      if ($id) {
        if ($existing) { $updated++; $action = 'updated'; }
        else { $inserted++; $action = 'inserted'; }
      } else {
        $errors++; $action = 'error';
        SLOG08("DBERR sku=$sku: ".$wpdb->last_error);
      }
    }
  }

  fputcsv($csv,[$sku,$offer['stock_total'] ?? 0,$offer['cost_usd'] ?? 0,$offer['exchange_rate'] ?? 0,$action], ',', '"', '\\');
}

fclose($csv);
SLOG08("Insertados=$inserted Actualizados=$updated SinCambio=$unchanged Errores=$errors");

function stage08_first(array $row, array $keys){
  foreach ($keys as $k) {
    if (isset($row[$k]) && trim((string)$row[$k]) !== '') {
      return trim((string)$row[$k]);
    }
  }
  return null;
}

function stage08_build_offer_payload(array $row): array {
  $cost = stage08_number($row, ['cost_usd','price_usd','su_precio','precio','precio_usd']);
  $fx   = stage08_number($row, ['exchange_rate','fx','tipo_cambio','exchange']);
  $stockTotal = stage08_int($row, ['stock_total','stock','existencias']);
  $stockMain  = stage08_int($row, ['stock_main','stock_a15','stock_others']);
  $stockTj    = stage08_int($row, ['stock_tijuana','stock_a15_tj']);

  $branches = null;
  if (!empty($row['stock_by_branch']) && is_array($row['stock_by_branch'])) {
    $branches = $row['stock_by_branch'];
  } elseif (!empty($row['stocks_by_branch']) && is_array($row['stocks_by_branch'])) {
    $branches = $row['stocks_by_branch'];
  }
  if ($branches) {
    $stockTotal = (int) array_sum(array_map('intval', $branches));
    $stockTj = 0;
    foreach ($branches as $name => $qty) {
      if (stripos((string)$name, 'tijuana') !== false || stripos((string)$name, 'tj') !== false) {
        $stockTj += (int)$qty;
      }
    }
    $stockMain = max(0, $stockTotal - $stockTj);
  }

  $currency = $cost > 0 ? 'USD' : null;

  $payload = [
    'cost_usd'             => $cost,
    'exchange_rate'        => $fx,
    'stock_total'          => $stockTotal,
    'stock_main'           => $stockMain,
    'stock_tijuana'        => $stockTj,
    'stock_by_branch_json' => $branches,
    'currency'             => $currency,
  ];
  if (isset($row['supplier'])) { $payload['supplier'] = $row['supplier']; }
  if (isset($row['warehouse_id'])) { $payload['warehouse_id'] = (int)$row['warehouse_id']; }
  if (isset($row['warehouse_code'])) { $payload['warehouse_code'] = (string)$row['warehouse_code']; }
  if (isset($row['lead_time_days'])) { $payload['lead_time_days'] = (int)$row['lead_time_days']; }
  if (isset($row['is_refurb'])) { $payload['is_refurb'] = (int)$row['is_refurb']; }
  if (isset($row['is_oem'])) { $payload['is_oem'] = (int)$row['is_oem']; }
  if (isset($row['is_bundle'])) { $payload['is_bundle'] = (int)$row['is_bundle']; }
  return $payload;
}

function stage08_number(array $row, array $keys): ?float {
  foreach ($keys as $key) {
    if (!isset($row[$key])) { continue; }
    $val = trim((string)$row[$key]);
    if ($val === '') { continue; }
    $val = str_replace([','], '.', $val);
    $val = preg_replace('/[^0-9\.-]/','', $val);
    if ($val === '' || $val === '-' || $val === '.') { continue; }
    return (float)$val;
  }
  return null;
}

function stage08_int(array $row, array $keys): ?int {
  foreach ($keys as $key) {
    if (!isset($row[$key])) { continue; }
    $val = trim((string)$row[$key]);
    if ($val === '') { continue; }
    $val = preg_replace('/[^0-9\-]/','', $val);
    if ($val === '' || $val === '-') { continue; }
    return (int)$val;
  }
  return null;
}

function stage08_has_changed(array $existing, array $offer): bool {
  $fields = ['cost_usd','exchange_rate','stock_total','stock_main','stock_tijuana'];
  foreach ($fields as $field) {
    $old = $existing[$field] ?? null;
    $new = $offer[$field] ?? null;
    if ($old === null && $new === null) { continue; }
    if ($field === 'exchange_rate' || $field === 'cost_usd') {
      if (abs(floatval($old) - floatval($new)) > 0.0001) { return true; }
    } else {
      if ((int)$old !== (int)$new) { return true; }
    }
  }
  $oldJson = $existing['stock_by_branch_json'] ?? null;
  $newJson = $offer['stock_by_branch_json'] ?? null;
  if (is_array($newJson)) { $newJson = wp_json_encode($newJson); }
  if ($oldJson != $newJson) { return true; }
  if (!empty($offer['product_id']) && (int)$offer['product_id'] !== (int)($existing['product_id'] ?? 0)) {
    return true;
  }
  return false;
}
