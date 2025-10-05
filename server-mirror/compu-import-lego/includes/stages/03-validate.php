<?php
if (!defined('ABSPATH')) { exit; }
class Compu_Stage_Validate {
  public function run($args){
    $run_id = compu_import_run_id_from_arg(isset($args['run-id']) ? $args['run-id'] : 'last');
    $base = compu_import_get_base_dir();
    $dir  = trailingslashit($base) . 'run-' . $run_id;
    $src  = $dir . '/normalized.jsonl';
    if (!file_exists($src)) \WP_CLI::error('Falta normalized.jsonl; corre normalize.');
    $rows = compu_import_read_jsonl($src);
    $out  = $dir . '/validated.jsonl';
    @unlink($out);
    $ok=0; $err=0;
    foreach ($rows as $r) {
      $errors = [];
      if (empty($r['sku']) && empty($r['supplier_sku'])) $errors[] = 'Sin SKU';
      if (!is_numeric($r['price_usd'])) $errors[] = 'price_usd inválido';
      if (!is_numeric($r['fx']))       $errors[] = 'fx inválido';
      if ($r['stock'] !== null && $r['stock'] < 0) $errors[] = 'stock negativo';
      if ($errors) {
        compu_import_log($run_id,'validate','error','Fila inválida: '.implode('; ', $errors), $r, isset($r['row_key'])?$r['row_key']:null);
        $err++; continue;
      }
// CompuStar guards
$reason = null;
if (compu_should_skip_row($r, $reason)) {
  if (function_exists('compu_import_log')) {
    compu_import_log($run_id, 'validate', 'error', $reason, $r, $r['row_key'] ?? null);
  }
  $err++;
  continue;
}

      compu_import_append_jsonl($out, $r);
      $ok++;
    }
    compu_import_log($run_id,'validate','info','Validación completa', ['ok'=>$ok,'err'=>$err]);
    \WP_CLI::success("Run {$run_id}: validadas OK={$ok} ERR={$err}");
  }
}
