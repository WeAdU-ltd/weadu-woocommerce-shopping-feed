<?php

/**
 * Plugin Name: WeAdU Shopping Feed Check
 * Description: Checks WooCommerce products for Google Shopping fields: title, GTIN, price, image, brand. Local only.
 * Version: 1.0.0
 * Author: WeAdU
 * Author URI: https://weadu.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: weadu-shopping-feed
 * Requires Plugins: woocommerce
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/FeedAudit.php';

add_action('before_woocommerce_init', 'weadu_sf_declare_hpos');
add_action('admin_menu', 'weadu_sf_menu');
add_action('admin_post_weadu_sf_csv', 'weadu_sf_download_csv');

function weadu_sf_menu()
{
    if (!class_exists('WooCommerce')) {
        add_menu_page(
            'WeAdU Shopping feed check',
            'Shopping feed check',
            'manage_options',
            'weadu-shopping-feed',
            'weadu_sf_missing_wc',
            'dashicons-search',
            56
        );
        return;
    }
    add_submenu_page(
        'woocommerce',
        'WeAdU Shopping feed check',
        'Shopping feed check',
        'manage_woocommerce',
        'weadu-shopping-feed',
        'weadu_sf_render_page'
    );
}

function weadu_sf_missing_wc()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You cannot open this page.', 'weadu-shopping-feed'), 403);
    }
    echo '<div class="wrap"><h1>WeAdU Shopping feed check</h1><p>'
        . esc_html__('WooCommerce has to be active.', 'weadu-shopping-feed') . '</p></div>';
}

function weadu_sf_render_page()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You cannot open this page.', 'weadu-shopping-feed'), 403);
    }
    $notice = '';
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['weadu_sf_settings'])) {
        // weadu_sf_save_settings() verifies the nonce.
        $notice = weadu_sf_save_settings();
    }
    $report = null;
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['weadu_sf_check'])) {
        check_admin_referer('weadu_sf_settings');
        $report = WeaduFeedAudit::auditItems(weadu_sf_collect(weadu_sf_limit()));
    }
    echo '<div class="wrap"><h1>WeAdU Shopping feed check</h1>';
    if ($notice !== '') {
        echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>';
    }
    echo '<p>' . esc_html__('The check runs in this admin. It does not call Google. Price is the price WooCommerce returns, and condition defaults to new when the product has no _condition meta. The id is the SKU, or the product id when the SKU is empty.', 'weadu-shopping-feed') . '</p>';
    echo '<form method="post">';
    wp_nonce_field('weadu_sf_settings');
    weadu_sf_field('weadu_sf_limit', __('Product limit (max 500)', 'weadu-shopping-feed'), (string) weadu_sf_limit());
    submit_button(__('Save settings', 'weadu-shopping-feed'), 'secondary', 'weadu_sf_settings', false);
    echo ' ';
    submit_button(__('Run check', 'weadu-shopping-feed'), 'primary', 'weadu_sf_check', false);
    echo '</form>';
    if (is_array($report)) {
        weadu_sf_render_report($report);
    }
    echo '</div>';
}

function weadu_sf_save_settings()
{
    check_admin_referer('weadu_sf_settings');
    $limit = isset($_POST['weadu_sf_limit']) ? (int) $_POST['weadu_sf_limit'] : 200;
    if ($limit < 1) {
        $limit = 200;
    }
    if ($limit > 500) {
        $limit = 500;
    }
    update_option('weadu_sf_limit', (string) $limit, false);
    return __('Settings saved.', 'weadu-shopping-feed');
}

function weadu_sf_limit()
{
    $limit = (int) get_option('weadu_sf_limit', 200);
    if ($limit < 1 || $limit > 500) {
        return 200;
    }
    return $limit;
}

function weadu_sf_collect($limit)
{
    if (!function_exists('wc_get_products')) {
        return array();
    }
    $products = wc_get_products(array(
        'status' => 'publish',
        'limit' => $limit,
        'orderby' => 'ID',
        'order' => 'ASC',
        'return' => 'objects',
    ));
    if (!is_array($products)) {
        return array();
    }
    $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
    $items = array();
    foreach ($products as $product) {
        if ($product instanceof WC_Product) {
            $items[] = weadu_sf_map_product($product, $currency);
        }
    }
    return $items;
}

function weadu_sf_map_product($product, $currency)
{
    $sku = trim((string) $product->get_sku());
    $id = $sku !== '' ? $sku : (string) $product->get_id();
    $description = (string) $product->get_description();
    if (trim(wp_strip_all_tags($description)) === '') {
        $description = (string) $product->get_short_description();
    }
    $gtin = '';
    if (method_exists($product, 'get_global_unique_id')) {
        $gtin = (string) $product->get_global_unique_id();
    }
    if ($gtin === '') {
        foreach (array('_gtin', '_ean') as $metaKey) {
            $candidate = (string) $product->get_meta($metaKey, true);
            if ($candidate !== '') {
                $gtin = $candidate;
                break;
            }
        }
    }
    $rawPrice = $product->get_price();
    $price = ($rawPrice === '' || $rawPrice === null) ? '' : number_format((float) $rawPrice, 2, '.', '');
    if ($price !== '' && $currency !== '') {
        $price .= ' ' . $currency;
    }
    $image = '';
    $imageId = $product->get_image_id();
    if ($imageId) {
        $url = wp_get_attachment_url($imageId);
        if (is_string($url)) {
            $image = $url;
        }
    }
    $link = (string) $product->get_permalink();
    $condition = trim((string) $product->get_meta('_condition', true));
    if ($condition === '') {
        $condition = 'new';
    }
    return array(
        'id' => $id,
        'title' => (string) $product->get_name(),
        'description' => $description,
        'link' => $link,
        'image_link' => $image,
        'availability' => $product->is_in_stock() ? 'in_stock' : 'out_of_stock',
        'price' => $price,
        'brand' => weadu_sf_brand($product),
        'gtin' => $gtin,
        'mpn' => (string) $product->get_meta('_mpn', true),
        'condition' => $condition,
        'identifier_exists' => '',
    );
}

function weadu_sf_brand($product)
{
    $terms = get_the_terms($product->get_id(), 'product_brand');
    if (is_array($terms) && isset($terms[0]->name)) {
        return (string) $terms[0]->name;
    }
    $attribute = (string) $product->get_attribute('pa_brand');
    if ($attribute !== '') {
        return $attribute;
    }
    return (string) $product->get_meta('_brand', true);
}

function weadu_sf_render_report($report)
{
    $iso = function_exists('determine_locale') ? determine_locale() : get_locale();
    $counts = $report['counts'];
    echo '<h2>' . esc_html__('Result', 'weadu-shopping-feed') . '</h2>';
    echo '<p>' . (int) $counts['items'] . ' ' . esc_html__('products', 'weadu-shopping-feed') . ', '
        . (int) $counts['errors'] . ' ' . esc_html__('errors', 'weadu-shopping-feed') . ', '
        . (int) $counts['warnings'] . ' ' . esc_html__('warnings', 'weadu-shopping-feed') . '.</p>';
    echo '<table class="widefat striped"><thead><tr><th>id</th><th>' . esc_html__('Title', 'weadu-shopping-feed')
        . '</th><th>' . esc_html__('Findings', 'weadu-shopping-feed') . '</th><th>'
        . esc_html__('Suggested title', 'weadu-shopping-feed') . '</th></tr></thead><tbody>';
    foreach ($report['items'] as $item) {
        $findings = array();
        foreach ($item['findings'] as $row) {
            $findings[] = $row['severity'] . ': ' . WeaduFeedAudit::label($row['code'], $iso);
        }
        echo '<tr><td>' . esc_html($item['id']) . '</td><td>' . esc_html($item['title']) . '</td><td>'
            . esc_html($findings ? implode('; ', $findings) : __('No finding', 'weadu-shopping-feed')) . '</td><td>'
            . esc_html($item['suggestedTitle']) . '</td></tr>';
    }
    echo '</tbody></table>';
    $url = wp_nonce_url(admin_url('admin-post.php?action=weadu_sf_csv'), 'weadu_sf_csv');
    echo '<p><a class="button" href="' . esc_url($url) . '">' . esc_html__('Download CSV', 'weadu-shopping-feed') . '</a></p>';
}

function weadu_sf_download_csv()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You cannot open this page.', 'weadu-shopping-feed'), 403);
    }
    check_admin_referer('weadu_sf_csv');
    $report = WeaduFeedAudit::auditItems(weadu_sf_collect(weadu_sf_limit()));
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="weadu-shopping-feed.csv"');
    // CSV body, not HTML: cells are neutralized against formula injection in WeaduFeedAudit::toCsv().
    echo WeaduFeedAudit::toCsv($report); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}

function weadu_sf_field($name, $label, $value)
{
    echo '<p><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label><br />';
    echo '<input class="regular-text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="'
        . esc_attr($value) . '" /></p>';
}

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (the plugin reads products only).
 */
function weadu_sf_declare_hpos()
{
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}

