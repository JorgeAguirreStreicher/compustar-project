<?php
if (!defined('ABSPATH')) { exit; } require_once __DIR__ . '/includes/helpers/helpers-lego.php';
if (!defined('ABSPATH')) { exit; } require_once __DIR__ . '/includes/helpers/helpers-lego.php';
if (!defined('ABSPATH')) { exit; } require_once __DIR__ . '/includes/helpers/helpers-lego.php';
/**
 * Plugin Name: Compu Import (LEGO)
 * Description: Importador modular por etapas para Syscom → WooCommerce. Comandos WP-CLI: wp compu import <stage>
 * Version: 1.0.0
 * Author: Jorge + Leo
 */
if (!defined('ABSPATH')) { exit; }
define('COMPU_IMPORT_DIR', __DIR__);
define('COMPU_IMPORT_UPLOAD_SUBDIR', 'compu-import');
define('COMPU_IMPORT_DEFAULT_CSV', '/home/compustar/ProductosHora.csv');
define('COMPU_IMPORT_MAPPING_CSV', '/home/compustar/compu_syscom_mapping.csv');
define('COMPU_IMPORT_TEST_META', '_compu10_test');
require_once COMPU_IMPORT_DIR . '/includes/class-compu-import-cli.php';
require_once COMPU_IMPORT_DIR . '/includes/helpers/helpers-common.php';
require_once COMPU_IMPORT_DIR . '/includes/helpers/helpers-db.php';
require_once COMPU_IMPORT_DIR . '/includes/helpers/helpers-tax.php';
require_once COMPU_IMPORT_DIR . '/includes/helpers/helpers-media.php';
require_once COMPU_IMPORT_DIR . '/includes/helpers/helpers-price.php';
foreach (glob(COMPU_IMPORT_DIR . '/includes/stages/*.php') as $stage_file) { require_once $stage_file; }
add_action('plugins_loaded', function() {
  $upload_dir = wp_upload_dir();
  $base = trailingslashit($upload_dir['basedir']) . COMPU_IMPORT_UPLOAD_SUBDIR;
  if (!file_exists($base)) { wp_mkdir_p($base); }
});
