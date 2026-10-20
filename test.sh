#!/bin/sh
# Local checks: PHP lint, shared fixtures through the PHP port, CSV export, WordPress.org basics.
set -eu
cd "$(dirname "$0")"
P=weadu-shopping-feed/weadu-shopping-feed.php
find . -name "*.php" -not -path "./.git/*" -print0 | xargs -0 -n1 php -l >/dev/null
node --test test/
php test/csv_test.php
php test/plugin_test.php
! grep -n "weadu_sf_t" "$P"
grep -q "declare_compatibility('custom_order_tables'" "$P"
grep -q "License: GPLv2 or later" "$P"
grep -q "^License: GPLv2 or later" weadu-shopping-feed/readme.txt
grep -q "^Tested up to: 7.1$" weadu-shopping-feed/readme.txt
php -r '$s=file_get_contents("weadu-shopping-feed/weadu-shopping-feed.php"); preg_match_all("/(?:esc_html__|__)\\(\x27(?:[^\x27\\\\]|\\\\.)*\x27, \x27([^\x27]+)\x27\\)/", $s, $m); if (!$m[1] || array_unique($m[1]) !== array("weadu-shopping-feed")) { exit(1); } echo count($m[1]), " strings in weadu-shopping-feed\n";'
echo "weadu-woocommerce-shopping-feed ok"
