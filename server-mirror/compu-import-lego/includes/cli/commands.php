<?php
if ( ! defined('WP_CLI') || ! WP_CLI ) { return; }

require_once __DIR__ . '/../helpers/helpers-common.php'; // compu_last_run_dir(), compu_pick_jsonl()

if (!function_exists('compu_ensure_run_dir')) {
  function compu_ensure_run_dir($run_dir) {
    if (!$run_dir || !is_dir($run_dir)) { $run_dir = compu_last_run_dir(); }
    if (!$run_dir || !is_dir($run_dir)) {
      WP_CLI::error("RUN_DIR no válido. Proporcione --run_dir o ejecute 02→06 primero.");
    }
    return $run_dir;
  }
}

WP_CLI::add_command('compu:offers', function($args, $assoc) {
  $run_dir = compu_ensure_run_dir($assoc['run_dir'] ?? getenv('RUN_DIR') ?: null);
  $input   = $assoc['input_jsonl'] ?? getenv('INPUT_JSONL') ?: compu_pick_jsonl($run_dir);
  putenv("RUN_DIR=$run_dir");
  putenv("INPUT_JSONL=$input");
  if (!function_exists('SLOG08')) { function SLOG08($m){ error_log("[stage08] $m"); } }
  require_once __DIR__ . '/../stages/08-offers.php';
  WP_CLI::success("offers done → $run_dir");
});

WP_CLI::add_command('compu:pricing', function($args, $assoc) {
  $run_dir = compu_ensure_run_dir($assoc['run_dir'] ?? getenv('RUN_DIR') ?: null);
  putenv("RUN_DIR=$run_dir");
  if (!function_exists('SLOG09')) { function SLOG09($m){ error_log("[stage09] $m"); } }
  require_once __DIR__ . '/../stages/09-pricing.php';
  WP_CLI::success("pricing done → $run_dir");
});
