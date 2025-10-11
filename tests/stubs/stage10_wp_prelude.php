<?php
$stage10_stub_base = __DIR__;
if (!defined('ABSPATH')) {
    define('ABSPATH', $stage10_stub_base . '/');
}
$stage10_stub_log_path = getenv('STAGE10_GUARD_LOG') ?: null;

function stage10_stub_log(string $message): void
{
    global $stage10_stub_log_path;
    if ($stage10_stub_log_path) {
        file_put_contents($stage10_stub_log_path, $message . "\n", FILE_APPEND);
    }
}

if (!function_exists('wc_get_product_id_by_sku')) {
    function wc_get_product_id_by_sku($sku)
    {
        return 123;
    }
}

if (!function_exists('wc_get_product')) {
    function wc_get_product($id)
    {
        return new class {
            public function get_regular_price() { return 150.0; }
            public function get_sale_price() { return ''; }
        };
    }
}

if (!function_exists('wc_format_decimal')) {
    function wc_format_decimal($value, $precision = 2)
    {
        return number_format((float) $value, (int) $precision, '.', '');
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title)
    {
        $title = strtolower((string) $title);
        $title = preg_replace('/[^a-z0-9]+/', '-', $title);
        return trim($title ?? '', '-') ?: 'item';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return false;
    }
}

$GLOBALS['__stage10_meta'] = [
    123 => ['_compu_last_applied_price' => '150.00'],
];

if (!function_exists('get_post_meta')) {
    function get_post_meta($postId, $key, $single = true)
    {
        return $GLOBALS['__stage10_meta'][$postId][$key] ?? '';
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($postId, $key, $value)
    {
        stage10_stub_log("update_post_meta {$key}={$value}");
        $GLOBALS['__stage10_meta'][$postId][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($postId, $key)
    {
        stage10_stub_log("delete_post_meta {$key}");
        unset($GLOBALS['__stage10_meta'][$postId][$key]);
        return true;
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($postId, $terms, $taxonomy, $append = false)
    {
        return $terms;
    }
}

if (!function_exists('term_exists')) {
    function term_exists($term, $taxonomy)
    {
        return ['term_id' => 77];
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy)
    {
        return ['term_id' => 88];
    }
}

if (!function_exists('media_sideload_image')) {
    function media_sideload_image($file, $postId, $desc = null, $return = 'id')
    {
        return 456;
    }
}

if (!function_exists('set_post_thumbnail')) {
    function set_post_thumbnail($postId, $attachmentId)
    {
        stage10_stub_log("set_post_thumbnail {$attachmentId}");
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr, $wp_error = false)
    {
        return $postarr['ID'] ?? 0;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($postId, $force_delete = false)
    {
        stage10_stub_log("wp_delete_post {$postId}");
    }
}

if (!function_exists('wc_delete_product_transients')) {
    function wc_delete_product_transients($postId)
    {
        stage10_stub_log("wc_delete_product_transients {$postId}");
    }
}
