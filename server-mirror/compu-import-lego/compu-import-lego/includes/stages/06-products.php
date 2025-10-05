<?php
/**
 * Stage 06 - products (stable writer)
 * - Escribe final/*.csv siempre.
 * - No modifica la BD (solo simula acciones).
 * - No carga WordPress ni depende de plugins/temas.
 * - Solo corre en CLI / WP-CLI; si se incluye desde web, retorna inmediatamente.
 */

// Solo ejecuta en CLI o WP-CLI; si entra por web (FPM/Apache) no corre.
if (!(PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI))) {
  return;
}

// Aísla cualquier salida accidental (por seguridad)
if (!ob_get_level()) { ob_start(); }

date_default_timezone_set('UTC');

// === Entradas por entorno ===
$runId    = getenv('RUN_ID') ?: 'unknown';
$runDir   = getenv('RUN_DIR') ?: '';
$limit    = (int) (getenv('LIMIT') ?: 0);
$offset   = (int) (getenv('OFFSET') ?: 0);
$inJsonl  = getenv('INPUT_JSONL');

// === Logger robusto a archivo (sin volcar a error_log para no ensuciar WP-CLI) ===
function s06_log($msg) {
  global $runDir;
  $line = '['.date('Y-m-d H:i:s')."] stage06: $msg\n";
  if ($runDir) {
    $logDir = rtrim($runDir, '/').'/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    $logf = $logDir.'/stage06.log';
    @file_put_contents($logf, $line, FILE_APPEND | LOCK_EX);
  }
}

// === Normalización de rutas de trabajo ===
if (!$runDir) {
  // fallback a la carpeta actual (no ideal, pero evita fatales)
  $runDir = __DIR__;
}
if (!$inJsonl) {
  $inJsonl = $runDir.'/resolved.jsonl';
}

$finalDir = $runDir.'/final';
if (!is_dir($finalDir)) {
  @mkdir($finalDir, 0775, true);
  @chmod($finalDir, 02775); // SGID para heredar grupo
}

s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");

// === Abrir CSVs de salida (cabeceras incluidas). fputcsv con 5 args (PHP>=8.4-safe)
$csvHeader   = ['sku','product_id','action','reason'];
$csvImported = @fopen($finalDir.'/imported.csv', 'w');
$csvUpdated  = @fopen($finalDir.'/updated.csv',  'w');
$csvSkipped  = @fopen($finalDir.'/skipped.csv',  'w');

if (!$csvImported || !$csvUpdated || !$csvSkipped) {
  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
  if ($csvImported) fclose($csvImported);
  if ($csvUpdated)  fclose($csvUpdated);
  if ($csvSkipped)  fclose($csvSkipped);
  if (ob_get_level()) { ob_end_clean(); }
  exit(1);
}

fputcsv($csvImported, $csvHeader, ",", "\"", "\\");
fputcsv($csvUpdated,  $csvHeader, ",", "\"", "\\");
fputcsv($csvSkipped,  $csvHeader, ",", "\"", "\\");
fflush($csvImported); fflush($csvUpdated); fflush($csvSkipped);
s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");

// === Validar/abrir input ===
if (!is_readable($inJsonl)) {
  s06_log("FATAL input no legible: $inJsonl");
  fclose($csvImported); fclose($csvUpdated); fclose($csvSkipped);
  if (ob_get_level()) { ob_end_clean(); }
  exit(2);
}
$fh = @fopen($inJsonl, 'r');
if (!$fh) {
  s06_log("FATAL no se pudo abrir $inJsonl");
  fclose($csvImported); fclose($csvUpdated); fclose($csvSkipped);
  if (ob_get_level()) { ob_end_clean(); }
  exit(3);
}

// === Procesamiento (solo decide imported/updated/skipped sin tocar BD) ===
$lineNo = 0; $processed = 0; $imported = 0; $updated = 0; $skipped = 0;

while (!feof($fh)) {
  $line = fgets($fh);
  if ($line === false) break;
  $lineNo++;
  if ($offset && $lineNo <= $offset) continue;

  $row = json_decode($line, true);
  if (!is_array($row)) {
    $skipped++;
    fputcsv($csvSkipped, ["", "", "skipped", "invalid_json"], ",", "\"", "\\");
    continue;
  }

  // SKU por prioridad
  $sku = "";
  foreach (['sku','model','codigo','code'] as $k) {
    if (!empty($row[$k])) { $sku = trim((string)$row[$k]); break; }
  }
  if ($sku === "") {
    $skipped++;
    fputcsv($csvSkipped, ["", "", "skipped", "missing_sku"], ",", "\"", "\\");
    continue;
  }

  $hasTitle = !empty($row['title']);
  $hasBrand = !empty($row['brand']);

  if ($hasTitle && $hasBrand) {
    $imported++;
    fputcsv($csvImported, [$sku, "", "imported", "safe-simulated"], ",", "\"", "\\");
  } elseif ($hasTitle && !$hasBrand) {
    $updated++;
    fputcsv($csvUpdated,  [$sku, "", "updated", "missing_brand_simulated"], ",", "\"", "\\");
  } else {
    $skipped++;
    fputcsv($csvSkipped,  [$sku, "", "skipped", "missing_title_brand"], ",", "\"", "\\");
  }

  $processed++;
  if ($limit && $processed >= $limit) break;
}
fclose($fh);

// === Cierre CSVs y log final ===
fflush($csvImported); fclose($csvImported);
fflush($csvUpdated);  fclose($csvUpdated);
fflush($csvSkipped);  fclose($csvSkipped);

s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");

// Descarta cualquier salida accidental que haya quedado
if (ob_get_level()) { ob_end_clean(); }
