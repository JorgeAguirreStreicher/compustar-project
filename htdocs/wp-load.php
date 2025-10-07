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

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }
        return (bool) @mkdir($path, 0775, true);
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type = 'mysql')
    {
        if ($type === 'timestamp') {
            return time();
        }
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512)
    {
        return json_encode($data, $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, $depth);
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        // No-op stub for CLI context.
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        // No-op stub for CLI context.
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value)
    {
        return $value;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var string */
        public $code;

        /** @var string */
        public $message;

        public function __construct(string $code = '', string $message = '')
        {
            $this->code = $code;
            $this->message = $message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
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

