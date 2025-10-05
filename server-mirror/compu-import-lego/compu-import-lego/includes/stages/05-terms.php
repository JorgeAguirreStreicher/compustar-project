<?php
if (!defined("COMP_RUN_STAGE")) { return; }
/**
 * Stage 05 - terms (mapeo contra tabla existente; no crea categorías)
 * Seguro para web: no ejecuta en solicitudes HTTP, sólo en CLI (cron/wp-cli).
 */

if (PHP_SAPI !== 'cli') {
  // Importante: si WordPress incluye este archivo en peticiones web, no hacer nada.
  return;
}

if (!defined('ABSPATH')) {
  // Cuando lo ejecutamos con wp-cli eval-file, ABSPATH ya debe estar definido tras load_wordpress().
  // Si lo invocamos con "php 05-terms.php" directo, necesitamos bootstrap mínimo.
  // Para simplificar, asumimos ejecución vía cron/wrapper o wp-cli. Si no, abortamos con mensaje claro.
  fwrite(STDERR, "[05-terms][FATAL] ABSPATH no definido. Ejecuta via cron/wrapper o wp-cli.\n");
  exit(1);
}

if (!function_exists('compu_stage05_terms_main')) {
  function compu_stage05_terms_main(): int {
    $run_id  = getenv('RUN_ID') ?: '';
    $run_dir = getenv('RUN_DIR') ?: '';
    $limit   = (int)(getenv('LIMIT') ?: 0);

    if (!$run_dir || !is_dir($run_dir)) {
      error_log("[05-terms] ERROR: RUN_DIR inválido: {$run_dir}");
      return 1;
    }

    $in_resolved  = $run_dir . '/resolved.jsonl';
    $in_validated = $run_dir . '/validated.jsonl';
    $out_terms    = $run_dir . '/terms_resolved.jsonl';

    $input_file = file_exists($in_resolved) ? $in_resolved : (file_exists($in_validated) ? $in_validated : '');
    if ($input_file === '') {
      error_log("[05-terms] ERROR: No existe ni resolved.jsonl ni validated.jsonl en {$run_dir}");
      return 1;
    }

    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb)) {
      error_log("[05-terms] ERROR: \$wpdb no disponible.");
      return 1;
    }

    $map_table = $wpdb->prefix . 'compu_catmap_syscom_map';
    // Chequeo rápido de tabla
    $exists = $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
      $map_table
    ));
    if (!$exists) {
      error_log("[05-terms] ERROR: Tabla de mapeo {$map_table} no existe.");
      return 1;
    }

    $in  = fopen($input_file, 'r');
    if (!$in) {
      error_log("[05-terms] ERROR: No se pudo abrir {$input_file}");
      return 1;
    }
    $out = fopen($out_terms,  'w');
    if (!$out) {
      fclose($in);
      error_log("[05-terms] ERROR: No se pudo abrir {$out_terms} para escritura");
      return 1;
    }

    $with_map = 0;
    $without  = 0;
    $total    = 0;

    while (!feof($in)) {
      $line = fgets($in);
      if ($line === false) break;
      $line = trim($line);
      if ($line === '') continue;

      $obj = json_decode($line, true);
      if (!is_array($obj)) continue;

      // Ajusta el campo del ID Syscom si difiere
      $sys_id = isset($obj['lvl3_id']) ? (int)$obj['lvl3_id'] : 0;

      $term_ids = [];
      if ($sys_id > 0) {
        $term_ids = $wpdb->get_col(
          $wpdb->prepare(
            "SELECT term_id FROM {$map_table} WHERE syscom_menu_id = %d AND active = 1",
            $sys_id
          )
        );
        $term_ids = array_values(array_unique(array_map('intval', $term_ids)));
      }

      if (!empty($term_ids)) {
        $obj['term_ids'] = $term_ids;
        $with_map++;
      } else {
        // Sin mapeo: 06 lo marcará como skipped (no_term_mapping_for_new_product)
        $without++;
      }

      fwrite($out, json_encode($obj, JSON_UNESCAPED_UNICODE) . "\n");

      $total++;
      if ($limit > 0 && $total >= $limit) break;
    }

    fclose($in);
    fclose($out);

    error_log("[05-terms] IN=" . basename($input_file) . " OUT=" . basename($out_terms) .
              " total={$total} with_map={$with_map} without_map={$without}");

    // Salida corta tipo JSON para tus logs
    echo json_encode([
      'stage'      => '05-terms',
      'run_id'     => $run_id,
      'input'      => basename($input_file),
      'output'     => basename($out_terms),
      'total'      => $total,
      'with_map'   => $with_map,
      'without'    => $without
    ], JSON_UNESCAPED_UNICODE) . "\n";

    return 0;
  }
}

// Ejecuta sólo en CLI
exit( compu_stage05_terms_main() );
