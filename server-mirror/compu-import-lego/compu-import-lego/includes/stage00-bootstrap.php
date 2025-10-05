<?php
if (!defined('COMP_RUN_STAGE')) { return; }
if (!((defined('WP_CLI') && WP_CLI) || (defined('COMP_RUN_STAGE') && COMP_RUN_STAGE))) { return; }
/** Stage 00: Bootstrap & Version Check */
if (!defined('COMP_IMPORT_VERSION')) { error_log('[compu-import] FALTA: COMP_IMPORT_VERSION'); return; }
$version_file = '/home/compustar/VERSION';
$expected = trim(@file_get_contents($version_file)) ?: '0.0.0';
$plugin_v = constant('COMP_IMPORT_VERSION');
if (version_compare($plugin_v, $expected, '!=')) {
  error_log(sprintf('[compu-import] Version mismatch: plugin=%s expected=%s (%s)', $plugin_v, $expected, $version_file));
  // return; // descomenta para bloquear si no coincide
} else {
  error_log(sprintf('[compu-import] Version OK: %s', $plugin_v));
}
