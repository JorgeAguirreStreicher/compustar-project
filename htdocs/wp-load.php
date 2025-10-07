<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string {
        return rtrim($value, "\\/") . '/';
    }
}

if (!function_exists('remove_accents')) {
    function remove_accents(string $value): string {
        return $value;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array {
        $base = getenv('WP_UPLOAD_BASE');
        if (!$base) {
            $base = ABSPATH . 'wp-content/uploads';
        }
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        return [
            'basedir' => rtrim($base, '/'),
            'baseurl' => 'http://example.com/uploads',
        ];
    }
}

if (!class_exists('wpdb')) {
    class wpdb
    {
        public $prefix = 'wp_';

        public function get_var($query)
        {
            return 0;
        }

        public function get_col($query)
        {
            return [];
        }

        public function insert($table, $data)
        {
        }

        public function update($table, $data, $where)
        {
        }

        public function query($sql)
        {
        }

        public function prepare($query, ...$args)
        {
            return $query;
        }
    }
}

global $wpdb;
if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
    $wpdb = new wpdb();
}

if (!function_exists('compu_import_now')) {
    function compu_import_now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
