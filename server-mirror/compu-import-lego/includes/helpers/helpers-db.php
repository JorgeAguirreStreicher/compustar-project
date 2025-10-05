<?php
if (!defined('ABSPATH')) { exit; }
function compu_import_tables_ensure() {
  global $wpdb; $p=$wpdb->prefix;
  $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}compu_lego_runs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, source VARCHAR(64), file_path TEXT, started_at DATETIME, finished_at DATETIME, status VARCHAR(32), ok_count INT DEFAULT 0, warn_count INT DEFAULT 0, error_count INT DEFAULT 0, notes TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}compu_lego_log_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, run_id BIGINT UNSIGNED NOT NULL, stage VARCHAR(64) NOT NULL, row_key VARCHAR(191), level VARCHAR(16) NOT NULL, message TEXT, raw_json LONGTEXT, created_at DATETIME, KEY run_id (run_id), KEY stage_row (stage,row_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}compu_offers (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NULL, source VARCHAR(64) NOT NULL, supplier_sku VARCHAR(191) NOT NULL, cost_usd DECIMAL(12,4) NULL, exchange_rate DECIMAL(10,4) NULL, stock_total INT NULL, stock_main INT NULL, stock_tijuana INT NULL, stock_by_branch_json LONGTEXT NULL, currency CHAR(3) NULL, offer_hash CHAR(32) NULL, valid_from DATETIME NULL, created_at DATETIME NULL, updated_at DATETIME NULL, supplier VARCHAR(50) NULL, warehouse_id INT NULL, warehouse_code VARCHAR(50) NULL, lead_time_days INT NULL, is_refurb TINYINT(1) DEFAULT 0, is_oem TINYINT(1) DEFAULT 0, is_bundle TINYINT(1) DEFAULT 0, UNIQUE KEY uniq_supplier (supplier_sku, source), KEY product_source (product_id, source)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function compu_import_run_open($source='syscom', $file_path=null){ compu_import_tables_ensure(); global $wpdb; $wpdb->insert($wpdb->prefix.'compu_lego_runs',['source'=>$source,'file_path'=>$file_path,'started_at'=>compu_import_now(),'status'=>'running']); return (int)$wpdb->insert_id; }
function compu_import_run_id_from_arg($run_id){ global $wpdb; if($run_id==='last' or !$run_id){$rid=(int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}compu_lego_runs ORDER BY id DESC LIMIT 1"); if(!$rid) throw new Exception('No hay runs previos. Ejecuta fetch/normalize primero.'); return $rid;} return (int)$run_id; }
function compu_import_run_close($run_id,$status='completed',$ok=0,$warn=0,$err=0){ global $wpdb; $wpdb->update($wpdb->prefix.'compu_lego_runs',['finished_at'=>compu_import_now(),'status'=>$status,'ok_count'=>$ok,'warn_count'=>$warn,'error_count'=>$err],['id'=>$run_id]); }
function compu_import_log($run_id,$stage,$level,$message,$raw=null,$row_key=null){ global $wpdb; $wpdb->insert($wpdb->prefix.'compu_lego_log_items',['run_id'=>$run_id,'stage'=>$stage,'row_key'=>$row_key,'level'=>$level,'message'=>$message,'raw_json'=>$raw?wp_json_encode($raw):null,'created_at'=>compu_import_now()]); if(defined('WP_CLI') && WP_CLI){ \WP_CLI::log("[".strtoupper($level)."][{$stage}] {$message}"); } }
function compu_offers_upsert($supplier_sku,$source,$offer){
  global $wpdb; $t=$wpdb->prefix.'compu_offers';
  $id=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE supplier_sku=%s AND source=%s",$supplier_sku,$source));
  $offer['updated_at']=compu_import_now();
  if($id){
    $wpdb->update($t,$offer,['id'=>$id]);
    return (int)$id;
  }
  $offer['supplier_sku']=$supplier_sku;
  $offer['source']=$source;
  $offer['created_at']=compu_import_now();
  $wpdb->insert($t,$offer);
  return (int)$wpdb->insert_id;
}
