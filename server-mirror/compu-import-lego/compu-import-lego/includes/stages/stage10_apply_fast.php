<?php
if (!defined('COMP_RUN_STAGE')) {
    define('COMP_RUN_STAGE', true);
}

if (php_sapi_name() !== 'cli' || (defined('WP_CLI') && !WP_CLI)) {
    // Solo se ejecuta en CLI; si no, terminamos silenciosamente.
    return;
}

if (!function_exists('wc_get_product_id_by_sku')) {
    fwrite(STDERR, "[stage10] WooCommerce no está cargado.\n");
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

function compu_stage10_read_payload(array $argv): array
{
    $args = array_slice($argv, 1);
    if (!$args) {
        throw new InvalidArgumentException('missing_payload');
    }
    $json = $args[0];
    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('invalid_payload');
    }
    return $data;
}

function compu_stage10_format_float($value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    return (float) $value;
}

function compu_stage10_format_int($value): int
{
    if ($value === null || $value === '') {
        return 0;
    }
    return (int) round((float) $value);
}

// [COMPUSTAR][ADD] helpers guardia precio cero
function compu_stage10_flag_enabled(string $name): bool
{
    $raw = getenv($name);
    if ($raw === false || $raw === '') {
        return true;
    }
    $normalized = strtolower(trim((string) $raw));
    if ($normalized === '') {
        return true;
    }
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function compu_stage10_value_truthy($value): ?bool
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
        if (in_array($normalized, ['1', 'true', 'yes', 'on', 'y'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off', 'n'], true)) {
            return false;
        }
    }
    return null;
}

function compu_stage10_should_skip_price(array $payload, float $price): bool
{
    if (!compu_stage10_flag_enabled('ST10_GUARD_PRICE_ZERO')) {
        return false;
    }
    if ($price <= 0.0) {
        return true;
    }
    if (array_key_exists('price_mxn_iva16_rounded', $payload) && array_key_exists('price_mxn_iva8_rounded', $payload)) {
        $p16 = (float) $payload['price_mxn_iva16_rounded'];
        $p08 = (float) $payload['price_mxn_iva8_rounded'];
        if ($p16 <= 0.0 && $p08 <= 0.0) {
            return true;
        }
    }
    if (array_key_exists('price_invalid', $payload)) {
        $normalized = compu_stage10_value_truthy($payload['price_invalid']);
        if ($normalized === true) {
            return true;
        }
    }
    return false;
}
// [/COMPUSTAR][ADD]

function compu_stage10_output(array $result, int $exitCode = 0): void
{
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($exitCode);
}

function compu_stage10_current_price(int $productId): ?float
{
    $raw = get_post_meta($productId, '_compu_last_applied_price', true);
    if ($raw === '') {
        return null;
    }
    return (float) $raw;
}

function compu_stage10_update_price(int $productId, float $price, ?float $salePrice, array &$actions, array &$skipped): void
{
    $applied = false;
    $current = compu_stage10_current_price($productId);
    if ($current === null || $price < $current) {
        $priceText = wc_format_decimal($price, 2);
        update_post_meta($productId, '_regular_price', $priceText);
        update_post_meta($productId, '_price', $priceText);
        if ($salePrice !== null && $salePrice > 0 && $salePrice < $price) {
            update_post_meta($productId, '_sale_price', wc_format_decimal($salePrice, 2));
        } else {
            delete_post_meta($productId, '_sale_price');
        }
        update_post_meta($productId, '_compu_last_applied_price', $priceText);
        update_post_meta($productId, '_compu_last_applied_price_ts', gmdate('c'));
        $actions[] = 'price_updated';
        $applied = true;
    } else {
        $skipped[] = 'price_not_lower';
    }

    if ($applied) {
        return;
    }
}

function compu_stage10_assign_category(int $productId, $categoryTerm, array &$actions, array &$skipped, array &$errors): bool
{
    $termId = is_array($categoryTerm) ? ($categoryTerm['term_id'] ?? null) : $categoryTerm;
    $termId = compu_stage10_format_int($termId);
    if ($termId <= 0) {
        $skipped[] = 'missing_category';
        return false;
    }
    $result = wp_set_object_terms($productId, [$termId], 'product_cat', false);
    if (is_wp_error($result)) {
        $errors[] = 'category_assignment_failed:' . $result->get_error_code();
        $skipped[] = 'missing_category';
        return false;
    }
    $actions[] = 'cat_assigned';
    return true;
}

function compu_stage10_set_stock(int $productId, array $payload, array &$actions): void
{
    $stock = compu_stage10_format_int($payload['stock'] ?? 0);
    $status = $payload['stock_status'] ?? ($stock > 0 ? 'instock' : 'outofstock');
    $status = in_array($status, ['instock', 'outofstock'], true) ? $status : ($stock > 0 ? 'instock' : 'outofstock');

    update_post_meta($productId, '_manage_stock', 'yes');
    update_post_meta($productId, '_stock', (string) $stock);
    update_post_meta($productId, '_stock_status', $status);
    update_post_meta($productId, '_backorders', 'no');
    $actions[] = 'stock_set';
}

function compu_stage10_set_audit_meta(int $productId, array $payload, array &$actions): void
{
    update_post_meta($productId, '_compu_last_stage10', gmdate('c'));
    if (!empty($payload['audit_hash'])) {
        update_post_meta($productId, '_compu_import_hash', (string) $payload['audit_hash']);
    }
    $actions[] = 'audit_meta';
}

function compu_stage10_set_brand(int $productId, string $brand, array &$actions, array &$errors): void
{
    $brand = trim($brand);
    if ($brand === '') {
        return;
    }
    $term = term_exists($brand, 'product_brand');
    if (!$term) {
        $created = wp_insert_term($brand, 'product_brand');
        if (is_wp_error($created)) {
            $errors[] = 'brand_create_failed:' . $created->get_error_code();
            return;
        }
        $termId = (int) ($created['term_id'] ?? 0);
    } else {
        $termId = (int) (is_array($term) ? ($term['term_id'] ?? 0) : $term);
    }
    if ($termId > 0) {
        wp_set_object_terms($productId, [$termId], 'product_brand', false);
        $actions[] = 'brand_assigned';
    }
}

function compu_stage10_import_image(int $productId, string $imageUrl, string $title, array &$actions, array &$errors): void
{
    $imageUrl = trim($imageUrl);
    if ($imageUrl === '') {
        return;
    }
    $attachmentId = media_sideload_image($imageUrl, $productId, $title, 'id');
    if (is_wp_error($attachmentId)) {
        $errors[] = 'featured_image_failed:' . $attachmentId->get_error_code();
        return;
    }
    set_post_thumbnail($productId, (int) $attachmentId);
    $actions[] = 'featured_image_set';
}

function compu_stage10_publish(int $productId, array &$actions, array &$errors): void
{
    $result = wp_update_post([
        'ID' => $productId,
        'post_status' => 'publish',
    ], true);
    if (is_wp_error($result)) {
        $errors[] = 'publish_failed:' . $result->get_error_code();
        return;
    }
    $actions[] = 'published';
}

try {
    $payload = compu_stage10_read_payload($argv);
    $sku = isset($payload['sku']) ? trim((string) $payload['sku']) : '';
    if ($sku === '') {
        throw new InvalidArgumentException('missing_sku');
    }

    $result = [
        'sku' => $sku,
        'id' => null,
        'actions' => [],
        'skipped' => [],
        'errors' => [],
    ];

    $shouldExist = !empty($payload['exists']);
    $productId = 0;
    if (!empty($payload['id'])) {
        $productId = (int) $payload['id'];
    }
    if ($productId <= 0) {
        $productId = wc_get_product_id_by_sku($sku) ?: 0;
    }

    if ($shouldExist && $productId <= 0) {
        $result['skipped'][] = 'product_missing';
        $result['errors'][] = 'product_not_found';
        compu_stage10_output($result, 0);
    }

    if ($productId > 0) {
        $result['id'] = $productId;
        if (!compu_stage10_assign_category($productId, $payload['category_term'] ?? null, $result['actions'], $result['skipped'], $result['errors'])) {
            compu_stage10_output($result, 0);
        }
        compu_stage10_set_stock($productId, $payload, $result['actions']);
        $price = compu_stage10_format_float($payload['price'] ?? 0.0);
        $salePrice = isset($payload['sale_price']) ? compu_stage10_format_float($payload['sale_price']) : null;
        if (compu_stage10_should_skip_price($payload, $price)) {
            $result['skipped'][] = 'price_zero_guard';
            $result['skipped_price_zero'] = true;
            compu_stage10_output($result, 0);
        }
        compu_stage10_update_price($productId, $price, $salePrice, $result['actions'], $result['skipped']);
        compu_stage10_set_audit_meta($productId, $payload, $result['actions']);
        compu_stage10_publish($productId, $result['actions'], $result['errors']);
        $result['actions'][] = 'updated';
        wc_delete_product_transients($productId);
        compu_stage10_output($result, 0);
    }

    // Producto nuevo
    if (!empty($payload['category_term'])) {
        $categoryTermId = compu_stage10_format_int($payload['category_term']);
        if ($categoryTermId <= 0) {
            $result['skipped'][] = 'missing_category';
            compu_stage10_output($result, 0);
        }
    } else {
        $result['skipped'][] = 'missing_category';
        compu_stage10_output($result, 0);
    }

    $title = isset($payload['title']) ? trim((string) $payload['title']) : '';
    if ($title === '') {
        $title = $sku;
    }
    $content = isset($payload['content']) ? (string) $payload['content'] : '';
    $excerpt = isset($payload['excerpt']) ? (string) $payload['excerpt'] : '';
    $slug = sanitize_title(isset($payload['slug']) ? (string) $payload['slug'] : $title);

    $postId = wp_insert_post([
        'post_title' => $title,
        'post_content' => $content,
        'post_excerpt' => $excerpt,
        'post_status' => 'draft',
        'post_type' => 'product',
        'post_name' => $slug !== '' ? $slug : sanitize_title($title),
    ], true);

    if (is_wp_error($postId)) {
        $result['errors'][] = 'create_failed:' . $postId->get_error_code();
        compu_stage10_output($result, 1);
    }

    $productId = (int) $postId;
    $result['id'] = $productId;

    update_post_meta($productId, '_sku', $sku);

    $categoryOk = compu_stage10_assign_category($productId, $payload['category_term'] ?? null, $result['actions'], $result['skipped'], $result['errors']);
    if (!$categoryOk) {
        wp_delete_post($productId, true);
        compu_stage10_output($result, 0);
    }

    compu_stage10_set_stock($productId, $payload, $result['actions']);

    $price = compu_stage10_format_float($payload['price'] ?? 0.0);
    $salePrice = isset($payload['sale_price']) ? compu_stage10_format_float($payload['sale_price']) : null;
    if (compu_stage10_should_skip_price($payload, $price)) {
        $result['skipped'][] = 'price_zero_guard';
        $result['skipped_price_zero'] = true;
        compu_stage10_output($result, 0);
    }
    compu_stage10_update_price($productId, $price, $salePrice, $result['actions'], $result['skipped']);

    wp_set_object_terms($productId, ['simple'], 'product_type', false);

    if (!empty($payload['brand'])) {
        compu_stage10_set_brand($productId, (string) $payload['brand'], $result['actions'], $result['errors']);
    }

    if (!empty($payload['image_url'])) {
        compu_stage10_import_image($productId, (string) $payload['image_url'], $title, $result['actions'], $result['errors']);
    }

    compu_stage10_set_audit_meta($productId, $payload, $result['actions']);
    compu_stage10_publish($productId, $result['actions'], $result['errors']);
    $result['actions'][] = 'created';
    wc_delete_product_transients($productId);

    compu_stage10_output($result, 0);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, '[stage10] invalid input: ' . $e->getMessage() . "\n");
    compu_stage10_output([
        'sku' => null,
        'id' => null,
        'actions' => [],
        'skipped' => [],
        'errors' => ['invalid_input:' . $e->getMessage()],
    ], 1);
} catch (Throwable $e) {
    fwrite(STDERR, '[stage10] fatal: ' . $e->getMessage() . "\n");
    compu_stage10_output([
        'sku' => null,
        'id' => null,
        'actions' => [],
        'skipped' => [],
        'errors' => ['exception:' . get_class($e)],
    ], 1);
}
