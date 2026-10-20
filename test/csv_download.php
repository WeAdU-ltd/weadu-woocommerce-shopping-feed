<?php
// Calls the CSV download handler, which ends with exit, in its own process.
require __DIR__ . '/wp_stubs.php';
class_alias('stdClass', 'WooCommerce');
$GLOBALS['wp']['options'] = array('weadu_sf_limit' => '200');
require __DIR__ . '/../weadu-shopping-feed/weadu-shopping-feed.php';
if (isset($argv[1]) && $argv[1] === 'nocap') {
    $GLOBALS['wp']['caps'] = array();
}
try {
    weadu_sf_download_csv();
} catch (WpDie $e) {
    echo 'DIE ' . $e->getCode() . ' ' . $e->getMessage();
}
