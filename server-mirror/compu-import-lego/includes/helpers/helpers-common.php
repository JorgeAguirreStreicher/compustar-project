<?php
if (!defined('ABSPATH')) { exit; }
function compu_import_get_base_dir() { $u = wp_upload_dir(); return trailingslashit($u['basedir']) . COMPU_IMPORT_UPLOAD_SUBDIR; }
function compu_import_mkdir($p) { if (!file_exists($p)) { wp_mkdir_p($p); } }
function compu_import_now() { return current_time('mysql'); }
function compu_import_detect_csv_delimiter($file) {
  $fh = @fopen($file, 'r'); if (!$fh) return ','; $line = fgets($fh); fclose($fh);
  $cand = [",",";","|","\t"]; $best=","; $bestc=0;
  foreach ($cand as $d) { $parts = explode($d=="\t"?"\t":$d, $line); if (count($parts)>$bestc){$bestc=count($parts);$best=$d=="\t"?"\t":$d;} }
  return $best;
}
function compu_import_row_hash($arr){ ksort($arr); return md5(json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
function compu_import_read_jsonl($path){ $o=[]; $fh=@fopen($path,'r'); if(!$fh) return $o; while(($l=fgets($fh))!==false){$l=trim($l); if($l==='')continue; $o[] = json_decode($l,true);} fclose($fh); return $o; }
function compu_import_append_jsonl($path,$row){ $fh=fopen($path,'a'); fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n"); fclose($fh); }
function compu_import_slug($s){ $s = remove_accents($s); $s=strtolower(preg_replace('/[^a-z0-9]+/i','-',$s)); return trim($s,'-'); }

if (!function_exists('compu_import_resolve_run_dir')) {
  /**
   * Resuelve el directorio base de ejecución para los stages.
   * Prioridad: opciones explícitas (run_dir, runDir, dir, path) antes de variables de entorno (RUN_DIR, RUN_PATH).
   *
   * @param array<string,mixed> $opts
   */
  function compu_import_resolve_run_dir(array $opts = []): string {
    $candidates = [];

    foreach (['run_dir', 'runDir', 'dir', 'path'] as $key) {
      if (!array_key_exists($key, $opts)) {
        continue;
      }
      $value = trim((string) $opts[$key]);
      if ($value === '') {
        continue;
      }
      $candidates[] = $value;
    }

    foreach (['RUN_DIR', 'RUN_PATH'] as $envKey) {
      $envValue = getenv($envKey);
      if ($envValue === false || $envValue === '') {
        continue;
      }
      $candidates[] = trim((string) $envValue);
    }

    foreach ($candidates as $candidate) {
      if ($candidate === '') {
        continue;
      }
      return rtrim($candidate, '/');
    }

    return '';
  }
}
