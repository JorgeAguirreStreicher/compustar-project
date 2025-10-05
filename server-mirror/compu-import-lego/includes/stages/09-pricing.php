<?php
if (!defined('COMP_RUN_STAGE')) { return; }
if (!((defined('WP_CLI') && WP_CLI) || (defined('COMP_RUN_STAGE') && COMP_RUN_STAGE))) { return; }

/**
 * Stage 09: Pricing Woo (usa columnas canónicas de compu_offers).
 */
global $wpdb;

$RUN_DIR = getenv('RUN_DIR');
$DEBUG   = getenv('DEBUG') ?: 0;
if (!$RUN_DIR) { fwrite(STDERR, "[09] Falta RUN_DIR\n"); return; }
@mkdir("$RUN_DIR/logs", 0775, true);
@mkdir("$RUN_DIR/final", 0775, true);
$LOG = fopen("$RUN_DIR/logs/stage09.log", "a");
function SLOG09($m){ global $LOG,$DEBUG; $l="[".date('Y-m-d H:i:s')."] $m\n"; if($LOG)fwrite($LOG,$l); if($DEBUG)echo $l; }

if (!function_exists('wc_get_product_id_by_sku')) { SLOG09("WooCommerce no cargado; abortando stage 09"); return; }

$IVA_DEFAULT = getenv('IVA_DEFAULT');
$IVA = is_numeric($IVA_DEFAULT) ? floatval($IVA_DEFAULT) : 16.0;
$table = $wpdb->prefix."compu_offers";

$csvSync = fopen("$RUN_DIR/final/woo_synced.csv","w");
$csvDrop = fopen("$RUN_DIR/final/price_dropped.csv","w");
$csvKeep = fopen("$RUN_DIR/final/unchanged.csv","w");
$csvErr  = fopen("$RUN_DIR/final/errors.csv","w");
fputcsv($csvSync,["sku","before","target","action"], ',', '"', '\\');
fputcsv($csvDrop,["sku","from","to"], ',', '"', '\\');
fputcsv($csvKeep,["sku","price"], ',', '"', '\\');
fputcsv($csvErr,["sku","reason"], ',', '"', '\\');

$ok=0;$drop=0;$keep=0;$err=0;

$offers = $wpdb->get_results("SELECT supplier_sku, cost_usd, exchange_rate, stock_total FROM {$table}", ARRAY_A);
foreach ($offers as $offer) {
  $sku = $offer['supplier_sku'] ?? null;
  if (!$sku) { continue; }

  $pid = wc_get_product_id_by_sku($sku);
  if (!$pid) { fputcsv($csvErr,[$sku,'product_not_found']); $err++; continue; }
  $product = wc_get_product($pid);
  if (!$product) { fputcsv($csvErr,[$sku,'wc_get_product_failed']); $err++; continue; }

  $costUsd = (float) ($offer['cost_usd'] ?? 0);
  $fx      = (float) ($offer['exchange_rate'] ?? 0);
  $stock   = (int)   ($offer['stock_total'] ?? 0);
  if ($costUsd <= 0 || $fx <= 0) { $fx = max($fx, 1.0); }

  $baseMxn = $costUsd * $fx;
  $margin  = 0.15; // TODO: enlazar con tabla de márgenes
  $target  = stage09_round_margin($baseMxn * (1+$margin) * (1 + $IVA/100));

  $before = stage09_current_price($product);
  $didUpdate = false;
  if ($before <= 0 || $target < $before) {
    $product->set_regular_price($target);
    $sale = $product->get_sale_price();
    if (!$sale || floatval($sale) >= $target) {
      $product->set_sale_price('');
    }
    $didUpdate = true;
  }

  $product->set_manage_stock(true);
  $product->set_stock_quantity(max(0,$stock));
  $product->set_stock_status($stock>0 ? 'instock' : 'outofstock');

  $product->save();

  if ($didUpdate) {
    fputcsv($csvSync,[$sku,$before,$target,'lower_or_set'], ',', '"', '\\');
    if ($before>0 && $target<$before) { fputcsv($csvDrop,[$sku,$before,$target], ',', '"', '\\'); $drop++; }
    $ok++;
  } else {
    fputcsv($csvKeep,[$sku,$before], ',', '"', '\\');
    $keep++;
  }
}

SLOG09("Pricing: ok=$ok drop=$drop keep=$keep err=$err");

function stage09_current_price($product){
  $regular = (float) $product->get_regular_price();
  $sale    = $product->get_sale_price();
  if ($sale !== '') {
    $saleVal = (float)$sale;
    if ($saleVal > 0 && $saleVal < $regular) { return $saleVal; }
  }
  return $regular;
}

function stage09_round_margin($price){
  $p = floor($price);
  $last = $p % 10;
  if ($last >= 9) { $p -= $last - 9; }
  elseif ($last >= 5) { $p -= $last - 5; }
  else { $p -= $last; }
  return max($p, 0);
}
