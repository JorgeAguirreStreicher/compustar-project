<?php
require_once dirname(__DIR__) . '/helpers/helpers-common.php';

/**
 * Stage 06 - Products (stable writer, modo simulación)
 *
 * Consume los JSONL normalizados/resueltos, valida el diccionario canónico
 * y produce CSVs de importación simulada. Acepta los nombres nuevos y mantiene
 * compatibilidad con alias heredados.
 */
if (!(PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI))) {
  return;
}

$RUN_ID  = getenv('RUN_ID') ?: 'unknown';
$RUN_DIR = rtrim((string) getenv('RUN_DIR'), '/');
$LIMIT   = getenv('LIMIT') !== false ? (int) getenv('LIMIT') : null;
$OFFSET  = getenv('OFFSET') !== false ? (int) getenv('OFFSET') : 0;

if ($RUN_DIR === '') {
  fwrite(STDERR, "[stage06] Falta RUN_DIR\n");
  return;
}

$logDir = $RUN_DIR.'/logs';
$finalDir = $RUN_DIR.'/final';
@mkdir($logDir, 0775, true);
@mkdir($finalDir, 0775, true);
$logFile = $logDir.'/stage06.log';
$LOG = fopen($logFile, 'a');

function s06_log(string $msg): void {
  global $LOG;
  $line = '['.date('Y-m-d H:i:s')."] stage06: $msg\n";
  if ($LOG) { fwrite($LOG, $line); }
}

s06_log("RUN_ID=$RUN_ID RUN_DIR=$RUN_DIR LIMIT=".($LIMIT ?? 'none')." OFFSET=$OFFSET");

$inResolved  = $RUN_DIR.'/resolved.jsonl';
$inValidated = $RUN_DIR.'/validated.jsonl';
$inputFile   = file_exists($inResolved) ? $inResolved : (file_exists($inValidated) ? $inValidated : null);
if (!$inputFile) {
  s06_log('ERROR: No se encontró resolved.jsonl ni validated.jsonl');
  return;
}

$csvHeader   = ['sku','product_id','action','reason'];
$csvImported = fopen($finalDir.'/imported.csv', 'w');
$csvUpdated  = fopen($finalDir.'/updated.csv',  'w');
$csvSkipped  = fopen($finalDir.'/skipped.csv',  'w');
foreach ([$csvImported,$csvUpdated,$csvSkipped] as $fh) {
  fputcsv($fh, $csvHeader, ',', '"', '\\');
}

$fh = fopen($inputFile, 'r');
if (!$fh) {
  s06_log("ERROR: No se pudo abrir $inputFile");
  fclose($csvImported); fclose($csvUpdated); fclose($csvSkipped);
  return;
}

$processed=0; $imported=0; $updated=0; $skipped=0; $lineNo=0;
while (!feof($fh)) {
  $line = fgets($fh);
  if ($line === false) { break; }
  $lineNo++;
  if ($OFFSET && $lineNo <= $OFFSET) { continue; }
  $row = json_decode($line, true);
  if (!is_array($row)) {
    $skipped++;
    fputcsv($csvSkipped, ['', '', 'skipped', 'invalid_json'], ',', '"', '\\');
    continue;
  }

  $sku = s06_first($row, ['sku','supplier_sku','model','codigo','code']);
  if ($sku === null || $sku === '') {
    $skipped++;
    fputcsv($csvSkipped, ['', '', 'skipped', 'missing_sku'], ',', '"', '\\');
    continue;
  }

  $lvl1 = s06_first($row, ['lvl1_id','id_n1','id_menu_nvl_1','lvl1','n1_id']);
  if ($lvl1 === null || $lvl1 === '' || $lvl1 === '---' || $lvl1 === '25') {
    $skipped++;
    fputcsv($csvSkipped, [$sku, '', 'skipped', 'blocked_lvl1'], ',', '"', '\\');
    continue;
  }

  $stockTotal = s06_stock_total($row);
  if ($stockTotal <= 0) {
    $skipped++;
    fputcsv($csvSkipped, [$sku, '', 'skipped', 'zero_stock_all'], ',', '"', '\\');
    continue;
  }

  $brand = s06_first($row, ['brand','marca']);
  $title = s06_first($row, ['title','titulo','name','nombre']);
  if ($title === null || $title === '') {
    $title = trim(($brand ?? '').' '.$sku);
  }

  if ($brand && $title) {
    $imported++;
    fputcsv($csvImported, [$sku, '', 'imported', 'simulated'], ',', '"', '\\');
  } elseif ($title) {
    $updated++;
    fputcsv($csvUpdated, [$sku, '', 'updated', 'missing_brand'], ',', '"', '\\');
  } else {
    $skipped++;
    fputcsv($csvSkipped, [$sku, '', 'skipped', 'missing_title'], ',', '"', '\\');
    continue;
  }

  $processed++;
  if ($LIMIT !== null && $processed >= $LIMIT) { break; }
}

fclose($fh);
fflush($csvImported); fclose($csvImported);
fflush($csvUpdated);  fclose($csvUpdated);
fflush($csvSkipped);  fclose($csvSkipped);

s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");

if ($LOG) { fclose($LOG); }

/**
 * Helpers
 * ------------------------------------------------------------------------
 */
function s06_first(array $row, array $keys): ?string {
  foreach ($keys as $key) {
    if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
      return trim((string)$row[$key]);
    }
  }
  return null;
}

function s06_stock_total(array $row): int {
  $candidates = [
    'stock_total','stock','existencias','inventario','stock_a15','stock_main','stock_others'
  ];
  $numbers = [];
  foreach ($candidates as $key) {
    if (isset($row[$key]) && $row[$key] !== '') {
      $numbers[] = (float) preg_replace('/[^0-9\.-]/','', (string)$row[$key]);
    }
  }
  if (isset($row['stock_by_branch']) && is_array($row['stock_by_branch'])) {
    foreach ($row['stock_by_branch'] as $qty) {
      $numbers[] = (float) $qty;
    }
  }
  if (isset($row['stocks_by_branch']) && is_array($row['stocks_by_branch'])) {
    foreach ($row['stocks_by_branch'] as $qty) {
      $numbers[] = (float) $qty;
    }
  }
  $total = 0.0;
  foreach ($numbers as $value) {
    $total += max(0.0, (float)$value);
  }
  return (int) round($total);
}
