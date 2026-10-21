<?php
// Small stand-ins for the WordPress and WooCommerce functions the plugin calls.
// They mimic only those calls; this is not a WordPress install.

define('ABSPATH', __DIR__ . '/');

class WpDie extends Exception
{
}

$GLOBALS['wp'] = array(
    'actions' => array(),
    'menus' => array(),
    'options' => array(),
    'caps' => array('manage_options' => true, 'manage_woocommerce' => true),
    'nonces_checked' => array(),
    'locale' => 'en_US',
    'products' => array(),
    'terms' => array(),
    'attachments' => array(),
    'currency' => 'EUR',
);

function add_action($hook, $callback) { $GLOBALS['wp']['actions'][$hook][] = $callback; }
function add_menu_page($pageTitle, $menuTitle, $cap, $slug, $callback) { $GLOBALS['wp']['menus'][] = array('top', $menuTitle, $cap, $slug, $callback); }
function add_submenu_page($parent, $pageTitle, $menuTitle, $cap, $slug, $callback) { $GLOBALS['wp']['menus'][] = array($parent, $menuTitle, $cap, $slug, $callback); }
function current_user_can($cap) { return !empty($GLOBALS['wp']['caps'][$cap]); }
function wp_die($message, $code = 500) { throw new WpDie($message, $code); }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
// Stand-in for WordPress gettext: a French locale reads the plugin's bundled fr_FR .po file.
function __($v, $domain = "default") {
    static $fr = null;
    if (strpos($GLOBALS['wp']['locale'], 'fr') !== 0 || $domain !== 'weadu-shopping-feed') { return (string) $v; }
    if ($fr === null) {
        $fr = array();
        $po = file_get_contents(__DIR__ . '/../weadu-shopping-feed/languages/weadu-shopping-feed-fr_FR.po');
        preg_match_all('/msgid "((?:[^"\\\\]|\\\\.)*)"\nmsgstr "((?:[^"\\\\]|\\\\.)*)"/', $po, $m, PREG_SET_ORDER);
        foreach ($m as $row) { if ($row[1] !== '') { $fr[stripcslashes($row[1])] = stripcslashes($row[2]); } }
    }
    return isset($fr[$v]) ? $fr[$v] : (string) $v;
}
function esc_html__($v, $domain = "default") { return esc_html(__($v, $domain)); }
function plugin_basename($f) { return basename(dirname($f)) . "/" . basename($f); }
function load_plugin_textdomain($d, $x = false, $p = "") { return true; }
function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_url($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function check_admin_referer($action) { $GLOBALS['wp']['nonces_checked'][] = $action; return 1; }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . $action . '" />'; }
function wp_nonce_url($url, $action) { return $url . '&_wpnonce=nonce-' . $action; }
function admin_url($path) { return 'https://shop.example/wp-admin/' . $path; }
function submit_button($text, $type, $name, $wrap) { echo '<input type="submit" class="button-' . $type . '" name="' . $name . '" value="' . esc_attr($text) . '" />'; }
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['wp']['options']) ? $GLOBALS['wp']['options'][$key] : $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['wp']['options'][$key] = $value; return true; }
function sanitize_text_field($v) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $v))); }
function wp_unslash($v) { return stripslashes((string) $v); }
function wp_strip_all_tags($v) { return trim(strip_tags((string) $v)); }
function determine_locale() { return $GLOBALS['wp']['locale']; }
function get_locale() { return $GLOBALS['wp']['locale']; }
function get_woocommerce_currency() { return $GLOBALS['wp']['currency']; }
function wp_get_attachment_url($id) { return isset($GLOBALS['wp']['attachments'][$id]) ? $GLOBALS['wp']['attachments'][$id] : false; }
function get_the_terms($id, $taxonomy) { return isset($GLOBALS['wp']['terms'][$id]) ? $GLOBALS['wp']['terms'][$id] : false; }
function nocache_headers() {}
function wc_get_products($args)
{
    $GLOBALS['wp']['last_query'] = $args;
    return array_slice($GLOBALS['wp']['products'], 0, $args['limit']);
}

class WC_Product
{
    public $data;
    public function __construct(array $data) { $this->data = $data; }
    private function v($k, $d = '') { return array_key_exists($k, $this->data) ? $this->data[$k] : $d; }
    public function get_id() { return $this->v('id', 0); }
    public function get_sku() { return $this->v('sku'); }
    public function get_name() { return $this->v('name'); }
    public function get_description() { return $this->v('description'); }
    public function get_short_description() { return $this->v('short_description'); }
    public function get_price() { return $this->v('price'); }
    public function get_image_id() { return $this->v('image_id', 0); }
    public function get_permalink() { return $this->v('permalink'); }
    public function is_in_stock() { return (bool) $this->v('in_stock', true); }
    public function get_attribute($name) { $a = $this->v('attributes', array()); return isset($a[$name]) ? $a[$name] : ''; }
    public function get_meta($key, $single = true) { $m = $this->v('meta', array()); return isset($m[$key]) ? $m[$key] : ''; }
}

class WC_Product_With_Gtin extends WC_Product
{
    public function get_global_unique_id() { return $this->data['global_unique_id']; }
}

function weadu_products()
{
    return array(
        new WC_Product_With_Gtin(array(
            'id' => 11, 'sku' => 'SHIRT-1', 'name' => 'Acme Cotton Shirt', 'description' => '<p>A cotton shirt for everyday wear.</p>',
            'price' => '29', 'image_id' => 5, 'permalink' => 'https://shop.example/product/shirt/', 'in_stock' => true,
            'global_unique_id' => '4006381333931',
        )),
        new WC_Product(array(
            'id' => 12, 'sku' => '', 'name' => '<script>x</script>', 'description' => '', 'short_description' => 'Short text.',
            'price' => '', 'image_id' => 0, 'permalink' => 'https://shop.example/product/hat/', 'in_stock' => false,
            'meta' => array('_ean' => '4006381333932', '_mpn' => 'H-1', '_brand' => 'MetaBrand', '_condition' => 'used'),
        )),
        new WC_Product(array(
            'id' => 13, 'sku' => 'SOCKS', 'name' => 'Acme Wool Socks Pack', 'description' => 'Warm socks.',
            'price' => '9.9', 'image_id' => 6, 'permalink' => 'https://shop.example/product/socks/', 'in_stock' => true,
            'attributes' => array('pa_brand' => 'AttrBrand'), 'meta' => array('_gtin' => '96385074'),
        )),
    );
}

$GLOBALS['wp']['attachments'] = array(5 => 'https://shop.example/wp-content/uploads/shirt.jpg', 6 => 'https://shop.example/wp-content/uploads/socks.jpg');
$GLOBALS['wp']['terms'] = array(11 => array((object) array('name' => 'Acme')));
$GLOBALS['wp']['products'] = weadu_products();
