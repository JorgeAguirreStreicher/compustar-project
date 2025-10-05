<?php
if (!defined("COMP_RUN_STAGE")) { return; }
if (!((defined('WP_CLI') && WP_CLI) || (defined('COMP_RUN_STAGE') && COMP_RUN_STAGE))) { return; }
/**
 * Stage 09: Pricing Woo (lower-only)
 */
global $wpdb;

$RUN_DIR = getenv('RUN_DIR'); $DEBUG = getenv('DEBUG') ?: 0;
if (!$RUN_DIR) { fwrite(STDERR, "[09] Falta RUN_DIR\n"); return; }
@mkdir("$RUN_DIR/logs", 0775, true); @mkdir("$RUN_DIR/final", 0775, true);
$LOG = fopen("$RUN_DIR/logs/stage09.log", "a");
function SLOG09($m){ global $LOG,$DEBUG; $l="[".date('Y-m-d H:i:s')."] $m\n"; if($LOG)fwrite($LOG,$l); if($DEBUG)echo $l; }

if (!function_exists('wc_get_product_id_by_sku')) { SLOG09("WooCommerce no cargado; abortando stage 09"); return; }

function compu_round_0_5_9_down_09($price){
  $p=floor($price); $last=$p%10;
  if($last>=9) $p-=$last-9; elseif($last>=5) $p-=$last-5; else $p-=$last;
  return max($p,0);
}
function compu_current_eff_price_09($p){
  $reg=floatval($p->get_regular_price()); $sal=$p->get_sale_price();
  $salv=(is_numeric($sal)&&floatval($sal)>0)?floatval($sal):null;
  return ($salv!==null && $salv<$reg) ? $salv : $reg;
}

$IVA_DEFAULT = getenv('IVA_DEFAULT'); $IVA = is_numeric($IVA_DEFAULT) ? floatval($IVA_DEFAULT) : 16.0;
$table = $wpdb->prefix."compu_offers";

$csv = fopen("$RUN_DIR/final/woo_synced.csv","w");
$csvd= fopen("$RUN_DIR/final/price_dropped.csv","w");
$csvk= fopen("$RUN_DIR/final/unchanged.csv","w");
$csve= fopen("$RUN_DIR/final/errors.csv","w");
fputcsv($csv,["sku","before","target","action"]);
fputcsv($csvd,["sku","from","to"]);
fputcsv($csvk,["sku","price"]);
fputcsv($csve,["sku","reason"]);

$ok=0;$drop=0;$keep=0;$err=0;

$offers = $wpdb->get_results("SELECT sku,cost_base,exchange_rate,stock FROM $table", ARRAY_A);
foreach($offers as $o){
  $sku=$o['sku']; if(!$sku)continue;
  $pid=wc_get_product_id_by_sku($sku); if(!$pid){fputcsv($csve,[$sku,"product_not_found"]);$err++;continue;}
  $p=wc_get_product($pid); if(!$p){fputcsv($csve,[$sku,"wc_get_product_failed"]);$err++;continue;}

  $base_mxn=floatval($o['cost_base'])*max(0.0,floatval($o['exchange_rate']));
  $margin=0.15; // TODO: conectar a tabla real si nos la das
  $target=compu_round_0_5_9_down_09($base_mxn*(1+$margin)*(1+$IVA/100.0));

  $before=compu_current_eff_price_09($p);
  $did=false;

  if($before<=0 || $target<$before){
    $p->set_regular_price($target);
    $sal=$p->get_sale_price(); if(!$sal || floatval($sal)>=$target){ $p->set_sale_price(''); }
    $did=true;
  }

  // Stock (sin ocultar)
  $stk=intval($o['stock']);
  $p->set_manage_stock(true);
  $p->set_stock_quantity(max(0,$stk));
  $p->set_stock_status($stk>0 ? 'instock' : 'outofstock');

  if($did || true){ $p->save(); }

  if($did){
    fputcsv($csv,[$sku,$before,$target,"lower_or_set"]);
    if($before>0 && $target<$before){ fputcsv($csvd,[$sku,$before,$target]); $drop++; }
    $ok++;
  } else {
    fputcsv($csvk,[$sku,$before]); $keep++;
  }
}
SLOG09("Pricing: ok=$ok drop=$drop keep=$keep err=$err");
