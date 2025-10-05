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
