<?php
if (!defined("COMP_RUN_STAGE")) { return; }
if (!((defined('WP_CLI') && WP_CLI) || (defined('COMP_RUN_STAGE') && COMP_RUN_STAGE))) { return; }
/**
 * Stage 08: Offers (upsert sobre wp_compu_offers con esquema real)
 */
global $wpdb;

$RUN_DIR = getenv('RUN_DIR'); $DEBUG = getenv('DEBUG') ?: 0;
if (!$RUN_DIR) { fwrite(STDERR, "[08] Falta RUN_DIR\n"); return; }
@mkdir("$RUN_DIR/logs", 0775, true); @mkdir("$RUN_DIR/final", 0775, true);

$LOG = fopen("$RUN_DIR/logs/stage08.log", "a");
function SLOG08($m){ global $LOG,$DEBUG; $l="[".date('Y-m-d H:i:s')."] $m\n"; if($LOG)fwrite($LOG,$l); if($DEBUG)echo $l; }

function compu_read_jsonl_08($p){
  $fh=@fopen($p,"r"); if(!$fh)return[];
  $a=[]; while(($l=fgets($fh))!==false){ $t=trim($l); if($t==='')continue; $o=json_decode($t,true); if(is_array($o))$a[]=$o; }
  fclose($fh); return $a;
}

SLOG08("== Stage 08: offers ==");
  // Helper: calcula existencias MAIN (todas menos Tijuana) y TJ
  function _syscom_split_stock_08(array $r): array {
    $cities_main = [
      "Chihuahua","Cd. Juárez","Guadalajara","Los Mochis","Mérida","México Norte","México Sur",
      "Monterrey","Puebla","Querétaro","Villahermosa","León","Hermosillo","San Luis Potosí",
      "Torreón","Chihuahua CEDIS","Toluca","Stock Querétaro CEDIS","Stock Veracruz","Stock Tepotzotlan",
      "Stock Cancun","Stock Culiacan","Stock Monterrey Centro"
    ];
    $toInt=function($v){ return is_numeric($v)? max(0,intval($v)) : 0; };
    $tj = $toInt($r["Tijuana"] ?? 0);
    $main=0; foreach($cities_main as $c){ $main += $toInt($r[$c] ?? 0); }
    return [$main,$tj];
  }
SLOG08("ENTER stage 08");
$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
$rows = $src ? compu_read_jsonl_08($src) : array();
if (empty($rows)) { SLOG08("No hay datos"); return; }

$table = $wpdb->prefix."compu_offers"; // esquema real
$out=fopen("$RUN_DIR/final/offers_upserted.csv","w");
fputcsv($out,["sku","stock","cost_usd","fx","action"]);

$ins=0;$upd=0;$unch=0;$err=0;

$skipped_no_pid=0;
foreach($rows as $r){
  // sku fuente (modelo)
  $sku = $r['sku'] ?? ($r['model'] ?? null);
  if(!$sku){ $err++; fputcsv($out,["",0,0,0,"error"]); SLOG08("ROW sin sku/model"); continue; }

  // mapa campos
  $cost = null; foreach(['Su Precio','su_precio','cost_base','precio_usd'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])){$cost=floatval($r[$k]);break;} }
  $fx   = null; foreach(['Tipo de Cambio','tipo_cambio','exchange_rate'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])){$fx=floatval($r[$k]);break;} }
  $stk  = null; foreach(['Existencias','existencias','stock'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])){$stk=intval($r[$k]);break;} }
  if($cost===null)$cost=0.0; if($fx===null)$fx=0.0; if($stk===null)$stk=0;

  // intentamos mapear al product_id por SKU de Woo (si existe)
  $pid = (function_exists('wc_get_product_id_by_sku')? wc_get_product_id_by_sku($sku) : 0);
  $warehouse_id = 15; $supplier = "SYSCOM"; $currency = "USD";
  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
  if($product_id<=0){ $err++; $act="error"; SLOG08("NOPROD sku=".$sku); fputcsv($out,[$sku,0,$supplier,15,"MAIN",0,0,$currency,$act]); continue; }
  $warehouse_id = 15; $supplier = "SYSCOM"; $currency = "USD";
  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
  $warehouse_id = 15; $supplier = "SYSCOM"; $currency = "USD";
  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
  $pid = intval($pid) ?: null;

  // columnas reales del schema
  // - supplier_sku := sku origen
  // - price_cost   := costo (lo tratamos como USD)
  // - currency     := 'USD' si cost>0
  // - stock        := stk
  // - last_synced_at := now
  // - product_id   := si se pudo resolver
  $now = current_time('mysql');
  $currency = ($cost > 0) ? 'USD' : null;

  // ¿existe ya una oferta con este supplier_sku?
  $row = $wpdb->get_row($wpdb->prepare("SELECT id, price_cost, currency, stock, product_id FROM $table WHERE supplier_sku=%s",$sku), ARRAY_A);

  if($row){
    $changed = (abs(floatval($row['price_cost']) - $cost) > 0.000001)
            || (($row['currency'] ?? null) !== $currency)
            || (intval($row['stock']) !== $stk)
            || (($pid !== null) && (intval($row['product_id']) !== $pid));

    $data = [
      'price_cost'    => $cost,
      'currency'      => $currency,
      'stock'         => $stk,
      'last_synced_at'=> $now,
    ];
    $fmt  = ['%f','%s','%d','%s'];

    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }

    $ok = $wpdb->update($table, $data, ['supplier_sku'=>$sku], $fmt, ['%s']);
    if($ok===false){ $err++; $act='error'; SLOG08("DBERR UPDATE sku=$sku err=".$wpdb->last_error); }
    else { $act = $changed ? 'updated' : 'unchanged'; $changed ? $upd++ : $unch++; }
  } else {
    $data = [
      'supplier_sku'  => $sku,
      'price_cost'    => $cost,
      'currency'      => $currency,
      'stock'         => $stk,
      'last_synced_at'=> $now,
      // opcionales con defaults
      'supplier'      => 'default',
      'warehouse_id'  => null,
      'warehouse_code'=> '',
      'lead_time_days'=> 0,
      'is_refurb'     => 0,
      'is_oem'        => 0,
      'is_bundle'     => 0,
    ];
    $fmt = ['%s','%f','%s','%d','%s','%s','%d','%s','%d','%d','%d','%d'];

    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }

    $ok = $wpdb->insert($table, $data, $fmt);
    if($ok===false){ $err++; $act='error'; SLOG08("DBERR INSERT sku=$sku err=".$wpdb->last_error); }
    else { $ins++; $act='inserted'; }
  }

  // CSV con la misma forma que tenías
  fputcsv($out,[$sku,$stk,$cost,$fx,$act]);
}

SLOG08("Insertados=$ins Actualizados=$upd SinCambio=$unch Errores=$err");
SLOG08("LEAVE stage 08");
