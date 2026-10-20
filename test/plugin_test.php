<?php
// Runs weadu-shopping-feed.php against small stand-ins for WordPress and WooCommerce.

require __DIR__ . '/harness.php';
require __DIR__ . '/wp_stubs.php';

$main = __DIR__ . '/../weadu-shopping-feed/weadu-shopping-feed.php';
$readme = file_get_contents(__DIR__ . '/../weadu-shopping-feed/readme.txt');
$header = file_get_contents($main);

// --- headers and readme -----------------------------------------------------
preg_match('/\* Version: (\S+)/', $header, $v);
preg_match('/Stable tag: (\S+)/', $readme, $s);
check('plugin header has a version', !empty($v[1]));
check('readme Stable tag matches the plugin version', isset($s[1]) && $s[1] === $v[1]);
check('plugin header declares the MIT license', contains($header, 'License: MIT'));
check('plugin header points to weadu.com', contains($header, 'Author URI: https://weadu.com'));
check('readme declares a license', (bool) preg_match('/^License: /m', $readme));
check('readme has Description, Installation, and Changelog', contains($readme, '== Description ==') && contains($readme, '== Installation ==') && contains($readme, '== Changelog =='));
$source = $header . file_get_contents(__DIR__ . '/../weadu-shopping-feed/includes/FeedAudit.php');
check('plugin source makes no outbound HTTP call', !preg_match('/wp_remote_|curl_|file_get_contents\(\s*[\'"]https?:|fsockopen/', $source));
check('folders have an index.php guard', file_exists(__DIR__ . '/../weadu-shopping-feed/index.php') && file_exists(__DIR__ . '/../weadu-shopping-feed/includes/index.php'));

require $main;

// --- hooks and menu ---------------------------------------------------------
check('admin_menu hook is registered', $GLOBALS['wp']['actions']['admin_menu'] === array('weadu_sf_menu'));
check('CSV download hook is registered', $GLOBALS['wp']['actions']['admin_post_weadu_sf_csv'] === array('weadu_sf_download_csv'));
weadu_sf_menu();
check('without WooCommerce a top-level notice page is added', $GLOBALS['wp']['menus'][0][0] === 'top' && $GLOBALS['wp']['menus'][0][4] === 'weadu_sf_missing_wc');
ob_start();
weadu_sf_missing_wc();
check('notice page says WooCommerce has to be active', contains(ob_get_clean(), 'WooCommerce has to be active.'));
class_alias('stdClass', 'WooCommerce');
$GLOBALS['wp']['menus'] = array();
weadu_sf_menu();
check('with WooCommerce the page sits under the WooCommerce menu', $GLOBALS['wp']['menus'][0][0] === 'woocommerce' && $GLOBALS['wp']['menus'][0][2] === 'manage_woocommerce');

function page()
{
    ob_start();
    try {
        weadu_sf_render_page();
    } catch (WpDie $e) {
        ob_end_clean();
        return 'DIE ' . $e->getCode();
    }
    return ob_get_clean();
}

// --- access control -----------------------------------------------------------
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = array();
$GLOBALS['wp']['caps'] = array('manage_options' => true);
check('users without manage_woocommerce are refused', page() === 'DIE 403');
$GLOBALS['wp']['caps'] = array('manage_options' => true, 'manage_woocommerce' => true);

// --- settings -----------------------------------------------------------------
$GLOBALS['wp']['options'] = array('weadu_sf_source' => '"><b>x');
$html = page();
check('page shows the settings form with a nonce', contains($html, 'name="weadu_sf_source"') && contains($html, 'nonce-weadu_sf_settings'));
check('stored values are escaped', contains($html, '&quot;&gt;&lt;b&gt;x') && !contains($html, '"><b>x'));
check('default limit is 200', contains($html, 'value="200"'));
check('page says it does not call Google', contains($html, 'It does not call Google.'));

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array('weadu_sf_settings' => '1', 'weadu_sf_source' => 'google ads', 'weadu_sf_medium' => 'cpc', 'weadu_sf_campaign' => 'spring', 'weadu_sf_limit' => '50');
$GLOBALS['wp']['nonces_checked'] = array();
$html = page();
check('saving checks the nonce', $GLOBALS['wp']['nonces_checked'] === array('weadu_sf_settings'));
check('invalid UTM value is refused', contains($html, 'UTM values were not saved.'));
check('invalid UTM value is not stored', get_option('weadu_sf_source') === '"><b>x');

$_POST = array('weadu_sf_settings' => '1', 'weadu_sf_source' => ' google ', 'weadu_sf_medium' => 'cpc', 'weadu_sf_campaign' => 'spring', 'weadu_sf_limit' => '9999');
$html = page();
check('valid settings are saved', contains($html, 'Settings saved.'));
check('UTM source is trimmed', get_option('weadu_sf_source') === 'google');
check('limit is capped at 500', get_option('weadu_sf_limit') === '500');
$_POST['weadu_sf_limit'] = '-3';
page();
check('negative limit falls back to 200', get_option('weadu_sf_limit') === '200');

// --- run the check ------------------------------------------------------------
$_POST = array('weadu_sf_check' => '1');
$html = page();
check('check reads published products only, by ID', $GLOBALS['wp']['last_query']['status'] === 'publish' && $GLOBALS['wp']['last_query']['orderby'] === 'ID');
check('check uses the saved limit', $GLOBALS['wp']['last_query']['limit'] === 200);
check('summary counts products, errors, and warnings', contains($html, '3 products, 3 errors, 0 warnings.'), strip_tags(substr($html, strpos($html, '<h2>'), 140)));
check('clean product shows no finding', contains($html, '<td>SHIRT-1</td><td>Acme Cotton Shirt</td><td>No finding</td>'));
check('product name is escaped', contains($html, '&lt;script&gt;x&lt;/script&gt;') && !contains($html, '<script>x</script>'));
check('id falls back to the product id', contains($html, '<td>12</td>'));
check('findings are listed with severity', contains($html, 'error: price is empty.') && contains($html, 'error: gtin check digit or length is invalid.') && contains($html, 'error: image_link is empty.'));
check('CSV link carries a nonce', contains($html, 'admin-post.php?action=weadu_sf_csv&amp;_wpnonce=nonce-weadu_sf_csv'));

$products = weadu_products();
$a = weadu_sf_map_product($products[0], 'EUR', 'google', 'cpc', 'spring');
check('link carries the saved UTM values', $a['link'] === 'https://shop.example/product/shirt/?utm_source=google&utm_medium=cpc&utm_campaign=spring', $a['link']);
check('global unique id is used as GTIN', $a['gtin'] === '4006381333931');
check('brand comes from the product_brand taxonomy', $a['brand'] === 'Acme');
check('price is formatted with the currency', $a['price'] === '29.00 EUR');
check('condition defaults to new', $a['condition'] === 'new');
check('image URL comes from the attachment', $a['image_link'] === 'https://shop.example/wp-content/uploads/shirt.jpg');
$b = weadu_sf_map_product($products[1], 'EUR', '', '', '');
check('short description is used when the description is empty', $b['description'] === 'Short text.');
check('_ean meta is used as GTIN', $b['gtin'] === '4006381333932');
check('_brand meta is the last brand fallback', $b['brand'] === 'MetaBrand');
check('_mpn and _condition meta are read', $b['mpn'] === 'H-1' && $b['condition'] === 'used');
check('empty price stays empty', $b['price'] === '');
check('out of stock is reported', $b['availability'] === 'out_of_stock');
check('link has no UTM when settings are empty', $b['link'] === 'https://shop.example/product/hat/');
$c = weadu_sf_map_product($products[2], 'GBP', '', '', '');
check('pa_brand attribute is the second brand fallback', $c['brand'] === 'AttrBrand');
check('_gtin meta is used as GTIN', $c['gtin'] === '96385074');
check('decimal price is padded', $c['price'] === '9.90 GBP');

// --- French admin -------------------------------------------------------------
$GLOBALS['wp']['locale'] = 'fr_FR';
$html = page();
check('French labels in a French admin', contains($html, 'Lancer le contrôle') && contains($html, 'Résultat'));
check('French finding labels', contains($html, 'error: Prix vide.'));
$GLOBALS['wp']['locale'] = 'en_US';

$csv = WeaduFeedAudit::toCsv(WeaduFeedAudit::auditItems(weadu_sf_collect(200)));
check('CSV export quotes a title with a comma', contains(WeaduFeedAudit::toCsv(array('items' => array(array('id' => '1', 'title' => 'Shirt, blue', 'findings' => array(), 'suggestedTitle' => '')))), '"Shirt, blue"'));

// --- CSV download (separate process, the handler ends with exit) ---------------
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/csv_download.php') . ' 2>&1');
check('CSV download starts with the header row', strpos((string) $out, "id,title,severity,code,field,message,suggested_title\n") === 0, substr((string) $out, 0, 200));
check('CSV download lists the findings', contains($out, 'gtin_invalid') && contains($out, 'SHIRT-1'));
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/csv_download.php') . ' nocap 2>&1');
check('CSV download refuses users without manage_woocommerce', strpos((string) $out, 'DIE 403') === 0, (string) $out);

finish();
