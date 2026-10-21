<?php
// Stand-in for WordPress wp_parse_url() outside WordPress.
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
}
