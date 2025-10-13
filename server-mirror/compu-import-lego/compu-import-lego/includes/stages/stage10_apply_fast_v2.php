<?php
if (!defined('COMP_RUN_STAGE')) {
    define('COMP_RUN_STAGE', true);
}

if (php_sapi_name() !== 'cli' || (defined('WP_CLI') && !WP_CLI)) {
    return;
}

$result = [
    'sku' => null,
    'id' => null,
    'actions' => [],
    'skipped' => [],
    'errors' => [],
];

$actions =& $result['actions'];
$skipped =& $result['skipped'];
$errors =& $result['errors'];

function compu_stage10_v2_flag_enabled(string $name, bool $default = true): bool
{
    $raw = getenv($name);
    if ($raw === false || $raw === '') {
        return $default;
    }
    $normalized = strtolower(trim((string) $raw));
    if ($normalized === '') {
        return $default;
    }
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function compu_stage10_v2_truthy($value): ?bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value)) {
        return $value !== 0;
    }
    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }
        if (in_array($normalized, ['1', 'true', 'yes', 'on', 'y', 'si', 'sí'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off', 'n'], true)) {
            return false;
        }
    }
    return null;
}

function compu_stage10_v2_output(array $result, int $exitCode = 0): void
{
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($exitCode);
}

function compu_stage10_v2_read_payload(array $argv): array
{
    $args = array_slice($argv, 1);
    if (!$args) {
        throw new InvalidArgumentException('missing_payload');
    }
    $json = $args[0];
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('invalid_payload');
    }
    return $decoded;
}

function compu_stage10_v2_extract_float($value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    return (float) $value;
}

function compu_stage10_v2_extract_int($value): int
{
    if ($value === null || $value === '') {
        return 0;
    }
    return (int) round((float) $value);
}

function compu_stage10_v2_set_image_from_url(int $productId, ?string $url, array &$actions, array &$skipped, array &$errors): void {
    $url = trim((string)($url ?? ''));
    if ($url === '') { return; }
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($url);
    if (is_wp_error($tmp)) { $skipped[] = 'image_download_failed'; return; }

    $filename = basename(parse_url($url, PHP_URL_PATH) ?: 'image.jpg');
    $file = ['name' => $filename, 'tmp_name' => $tmp];
    $id = media_handle_sideload($file, $productId);
    if (is_wp_error($id)) {
        @unlink($tmp);
        $skipped[] = 'image_attach_failed';
        return;
    }
    set_post_thumbnail($productId, $id);
    $actions[] = 'image_set';
}

function compu_stage10_v2_assign_brand(int $productId, ?string $brand, array &$actions, array &$skipped): void {
    $brand = trim((string)($brand ?? ''));
    if ($brand === '') { return; }
    $tax = taxonomy_exists('product_brand') ? 'product_brand'
        : (taxonomy_exists('pwb-brand') ? 'pwb-brand'
        : (taxonomy_exists('yith_product_brand') ? 'yith_product_brand' : null));
    if (!$tax) { $skipped[] = 'brand_tax_missing'; return; }
    wp_set_object_terms($productId, [$brand], $tax, false);
    $actions[] = 'brand_assigned';
}

function compu_stage10_v2_calc_price_mxn_vat(array $payload): float {
    $price = (float)($payload['price'] ?? 0);
    if ($price > 0) { return round($price, 2); }

    $tc   = (float)($payload['Tipo_de_Cambio'] ?? 1);
    $base = (float)($payload['Su_Precio'] ?? 0);
    if ($base <= 0) {
        $base = (float)($payload['Precio_Especial'] ?? ($payload['Precio_Lista'] ?? 0));
    }
    if ($base <= 0) { return 0.0; }
    $mxn = $tc > 0 ? ($base * $tc) : $base;
    $mxn_iva = $mxn * 1.16; // IVA 16%
    return round($mxn_iva, 2);
}

function compu_stage10_v2_upsert_offer(int $productId, array $payload, array &$actions, array &$errors): void {
    global $wpdb;
    $table = $wpdb->prefix . 'compu_offers';

    $supplier = strtolower(trim((string)($payload['supplier'] ?? 'syscom')));
    $supplierSku = (string)($payload['supplier_sku'] ?? ($payload['sku'] ?? ''));
    $exchange = (float)($payload['Tipo_de_Cambio'] ?? 1);
    $cost = (float)($payload['Su_Precio'] ?? 0);
    if ($cost <= 0) {
        $cost = (float)($payload['Precio_Especial'] ?? ($payload['Precio_Lista'] ?? 0));
    }
    $cost_mxn = $exchange > 0 ? ($cost * $exchange) : $cost;
    $cost_vat = $cost_mxn * 1.16;
    $stock = (int)($payload['Stock_Suma_Total'] ?? ($payload['Stock_Suma_Sin_Tijuana'] ?? ($payload['stock'] ?? 0)));
    $warehouse = isset($payload['warehouse_code']) ? (string)$payload['warehouse_code'] : null;

    $data = [
        'product_id'       => $productId,
        'supplier'         => $supplier,
        'supplier_sku'     => $supplierSku,
        'source'           => (string)($payload['source'] ?? 'stage10_fast_v2'),
        'exchange_rate'    => $exchange,
        'price_cost'       => $cost,
        'price_cost_mxn'   => $cost_mxn,
        'price_cost_vat'   => $cost_vat,
        'stock'            => $stock,
        'last_synced_at'   => current_time('mysql', true),
        'warehouse_code'   => $warehouse,
        'is_new'           => 1,
        'is_refurb'        => 0,
        'is_bundle'        => 0,
    ];
    $fmt = ['%d','%s','%s','%s','%f','%f','%f','%d','%s','%s','%d','%d','%d'];

    $ok = $wpdb->replace($table, $data, $fmt);
    if ($ok === false) {
        $errors[] = 'offer_upsert_failed:' . $wpdb->last_error;
    } else {
        $actions[] = 'offer_upserted';
    }
}

function compu_stage10_v2_pick_title(array $payload, string $fallback): string
{
    foreach (['name', 'title', 'Nombre', 'post_title'] as $key) {
        if (!empty($payload[$key])) {
            $candidate = trim((string) $payload[$key]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }
    return $fallback;
}

function compu_stage10_v2_pick_content(array $payload): string
{
    foreach (['description', 'content', 'Descripcion', 'post_content'] as $key) {
        if (!empty($payload[$key])) {
            $candidate = trim((string) $payload[$key]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }
    return '';
}

function compu_stage10_v2_assign_category(int $productId, int $termId, array &$actions, array &$skipped, array &$errors): void
{
    $result = wp_set_object_terms($productId, [$termId], 'product_cat', false);
    if (is_wp_error($result)) {
        $errors[] = 'category_assignment_failed:' . $result->get_error_code();
        $skipped[] = 'missing_category';
        return;
    }
    $actions[] = 'cat_assigned';
}

function compu_stage10_v2_set_stock(int $productId, array $payload, array &$actions): void
{
    $stock = compu_stage10_v2_extract_int($payload['stock'] ?? 0);
    $status = isset($payload['stock_status']) ? strtolower((string) $payload['stock_status']) : '';
    if ($status !== 'instock' && $status !== 'outofstock') {
        $status = $stock > 0 ? 'instock' : 'outofstock';
    }
    $manage = compu_stage10_v2_truthy($payload['manage_stock'] ?? null);
    $manage = $manage !== false;

    update_post_meta($productId, '_manage_stock', $manage ? 'yes' : 'no');
    update_post_meta($productId, '_stock', (string) max(0, $stock));
    update_post_meta($productId, '_stock_status', $status);
    update_post_meta($productId, '_backorders', 'no');
    if (function_exists('wc_update_product_stock_status')) {
        wc_update_product_stock_status($productId, $status);
    }
    $actions[] = 'stock_set';
}

function compu_stage10_v2_apply_price(int $productId, float $price, ?float $salePrice, array &$actions, array &$skipped): void
{
    $regularText = wc_format_decimal($price, 2);
    if ($salePrice !== null && $salePrice > 0 && $salePrice < $price) {
        $saleText = wc_format_decimal($salePrice, 2);
        update_post_meta($productId, '_sale_price', $saleText);
        update_post_meta($productId, '_price', $saleText);
    } else {
        delete_post_meta($productId, '_sale_price');
        update_post_meta($productId, '_price', $regularText);
        if ($salePrice !== null && $salePrice <= 0) {
            $skipped[] = 'sale_price_invalid';
        }
    }
    update_post_meta($productId, '_regular_price', $regularText);
    update_post_meta($productId, '_compu_last_applied_price', $regularText);
    update_post_meta($productId, '_compu_last_applied_price_ts', gmdate('c'));
    $actions[] = 'price_updated';
}

function compu_stage10_v2_set_audit_meta(int $productId, array $payload, array &$actions): void
{
    update_post_meta($productId, '_compu_last_stage10', gmdate('c'));
    if (!empty($payload['audit_hash'])) {
        update_post_meta($productId, '_compu_import_hash', (string) $payload['audit_hash']);
    }
    $actions[] = 'audit_meta';
}

function compu_stage10_v2_create_product(array $payload, string $sku, array &$actions, array &$errors): int
{
    $title = compu_stage10_v2_pick_title($payload, $sku ?: 'Producto nuevo');
    $postArgs = [
        'post_title' => $title,
        'post_type' => 'product',
        'post_status' => 'draft',
        'post_content' => compu_stage10_v2_pick_content($payload),
    ];
    $inserted = wp_insert_post($postArgs, true);
    if (is_wp_error($inserted)) {
        $errors[] = 'create_failed:' . $inserted->get_error_code();
        return 0;
    }
    $productId = (int) $inserted;
    if ($sku !== '') {
        update_post_meta($productId, '_sku', $sku);
    }
    $actions[] = 'created';
    return $productId;
}

try {
    $payload = compu_stage10_v2_read_payload($argv);
} catch (InvalidArgumentException $ex) {
    $errors[] = 'invalid_payload:' . $ex->getMessage();
    fwrite(STDERR, '[stage10_v2] payload error: ' . $ex->getMessage() . PHP_EOL);
    compu_stage10_v2_output($result, 1);
}

$result['sku'] = isset($payload['sku']) ? (string) $payload['sku'] : '';

if (!function_exists('wc_get_product_id_by_sku')) {
    fwrite(STDERR, "[stage10_v2] WooCommerce no está cargado.\n");
    $errors[] = 'woocommerce_not_loaded';
    compu_stage10_v2_output($result, 1);
}

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

$categoryTerm = compu_stage10_v2_extract_int($payload['category_term'] ?? 0);
if ($categoryTerm <= 0) {
    $skipped[] = 'skipped_no_cat';
    compu_stage10_v2_output($result);
}

$productId = compu_stage10_v2_extract_int($payload['product_id'] ?? ($payload['id'] ?? 0));
if ($productId <= 0 && $result['sku'] !== '') {
    $productId = (int) wc_get_product_id_by_sku($result['sku']);
}

// Mapear stock desde sumatorias de Syscom y forzar manage_stock
$payload['stock'] = (int)($payload['Stock_Suma_Total'] ?? ($payload['Stock_Suma_Sin_Tijuana'] ?? 0));
$payload['manage_stock'] = true;
// Peso en kg a meta nativa
if (!empty($payload['Peso_Kg']) && $productId > 0) {
    update_post_meta($productId, '_weight', (string)compu_stage10_v2_extract_float($payload['Peso_Kg']));
    $actions[] = 'weight_set';
}

$price = compu_stage10_v2_calc_price_mxn_vat($payload);
if (compu_stage10_v2_flag_enabled('ST10_GUARD_PRICE_ZERO', true) && $price <= 0.0) {
    $skipped[] = 'price_zero_guard';
    compu_stage10_v2_output($result);
}

$salePrice = null;
if (array_key_exists('sale_price', $payload)) {
    $saleCandidate = compu_stage10_v2_extract_float($payload['sale_price']);
    if ($saleCandidate > 0) {
        $salePrice = $saleCandidate;
    }
}

if ($productId <= 0) {
    if (!compu_stage10_v2_flag_enabled('ST10_ALLOW_CREATE', false)) {
        $skipped[] = 'missing_product';
        compu_stage10_v2_output($result);
    }
    $productId = compu_stage10_v2_create_product($payload, $result['sku'], $actions, $errors);
    if ($productId <= 0) {
        compu_stage10_v2_output($result, 1);
    }
}

$result['id'] = $productId;

if (!empty($payload['Peso_Kg'])) {
    update_post_meta($productId, '_weight', (string)compu_stage10_v2_extract_float($payload['Peso_Kg']));
    if (!in_array('weight_set', $actions, true)) {
        $actions[] = 'weight_set';
    }
}

compu_stage10_v2_assign_brand($productId, ($payload['Marca'] ?? $payload['brand'] ?? null), $actions, $skipped);

compu_stage10_v2_assign_category($productId, $categoryTerm, $actions, $skipped, $errors);
compu_stage10_v2_set_stock($productId, $payload, $actions);
$img = $payload['Imagen_Principal'] ?? ($payload['image_url'] ?? '');
compu_stage10_v2_set_image_from_url($productId, $img, $actions, $skipped, $errors);
compu_stage10_v2_apply_price($productId, $price, $salePrice, $actions, $skipped);
compu_stage10_v2_set_audit_meta($productId, $payload, $actions);
compu_stage10_v2_upsert_offer($productId, $payload, $actions, $errors);

$currentStatus = get_post_status($productId) ?: 'draft';
if ($currentStatus !== 'publish') {
    $update = wp_update_post([
        'ID' => $productId,
        'post_status' => 'publish',
    ], true);
    if (is_wp_error($update)) {
        $errors[] = 'publish_failed:' . $update->get_error_code();
    } else {
        $actions[] = 'published';
    }
}

if (!in_array('created', $actions, true)) {
    $actions[] = 'updated';
}

if (function_exists('wc_delete_product_transients')) {
    wc_delete_product_transients($productId);
}

compu_stage10_v2_output($result);
