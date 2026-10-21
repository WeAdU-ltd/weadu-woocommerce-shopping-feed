<?php
/**
 * CSV export: formula neutralization and no fputcsv deprecation.
 */
error_reporting(E_ALL);
set_error_handler(function ($no, $msg) { fwrite(STDERR, "PHP notice: $msg\n"); exit(1); });
require __DIR__ . '/../weadu-shopping-feed/includes/FeedAudit.php';
$report = array('items' => array(
    array('id' => '=HYPERLINK("x")', 'title' => '+cmd', 'suggestedTitle' => '@a', 'findings' => array()),
    array('id' => '2', 'title' => '-1', 'suggestedTitle' => 'Plain', 'findings' => array(
        array('severity' => 'error', 'code' => 'missing_gtin', 'field' => 'gtin', 'message' => '=1+1'),
    )),
));
$csv = WeaduFeedAudit::toCsv($report);
$fail = 0;
foreach (array("'=HYPERLINK", "'+cmd", "'@a", "'-1", "'=1+1", 'Plain') as $needle) {
    if (strpos($csv, $needle) === false) { fwrite(STDERR, "missing $needle\n"); $fail++; }
}
if (preg_match('/(^|,)[=+@-]/m', $csv)) { fwrite(STDERR, "unescaped formula cell\n"); $fail++; }
if ($fail) { exit(1); }
echo "csv ok\n";
