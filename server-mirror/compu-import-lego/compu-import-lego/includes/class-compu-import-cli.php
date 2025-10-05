<?php
if (!defined("WP_CLI") || !WP_CLI || !class_exists("WP_CLI") || !method_exists("WP_CLI","add_command")) { return; }
if (!defined("WP_CLI") || !WP_CLI || !class_exists("WP_CLI")) { return; }
if (!defined('ABSPATH')) { exit; }
// Solo registrar comandos si existe la clase y el método add_command del WP-CLI real
if (!defined('WP_CLI') || !WP_CLI || !class_exists('WP_CLI') || !method_exists('WP_CLI','add_command')) {
    return;
}

if (defined('WP_CLI') && WP_CLI) {
  class Compu_Import_CLI {
    public function import($args, $assoc_args) {
      list($stage) = $args;
      $stage = strtolower($stage);
      if ($stage === 'run') {
        $pipeline = ['fetch','normalize','validate','resolve-map','terms','products','media','offers','pricing','publish','report'];
        $from = $assoc_args['from'] ?? 'normalize';
        $to   = $assoc_args['to'] ?? 'offers';
        $start = array_search($from, $pipeline);
        $end   = array_search($to, $pipeline);
        if ($start === false || $end === false || $start > $end) { WP_CLI::error("Rango inválido"); }
        for ($i=$start; $i<=$end; $i++) { $this->dispatch($pipeline[$i], $assoc_args); }
        return;
      }
      $this->dispatch($stage, $assoc_args);
    }
    private function dispatch($stage, $assoc_args) {
      switch ($stage) {
        case 'fetch':        (new Compu_Stage_Fetch())->run($assoc_args); break;
        case 'normalize':    (new Compu_Stage_Normalize())->run($assoc_args); break;
        case 'validate':     (new Compu_Stage_Validate())->run($assoc_args); break;
        case 'resolve-map':  (new Compu_Stage_Resolve_Map())->run($assoc_args); break;
        case 'terms':        (new Compu_Stage_Terms())->run($assoc_args); break;
        case 'products':     (new Compu_Stage_Products())->run($assoc_args); break;
        case 'media':        (new Compu_Stage_Media())->run($assoc_args); break;
        case 'offers':       (new Compu_Stage_Offers())->run($assoc_args); break;
        case 'pricing':      (new Compu_Stage_Pricing())->run($assoc_args); break;
        case 'publish':      (new Compu_Stage_Publish())->run($assoc_args); break;
        case 'report':       (new Compu_Stage_Report())->run($assoc_args); break;
        default: WP_CLI::error("Stage desconocido: {$stage}");
      }
    }
  }
  WP_CLI::add_command('compu import', ['Compu_Import_CLI', 'import']);
}
