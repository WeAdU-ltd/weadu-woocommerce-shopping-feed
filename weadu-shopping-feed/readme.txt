=== WeAdU Shopping Feed Check ===
Contributors: weadu
Tags: woocommerce, google shopping
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Checks WooCommerce product fields used by a Google Shopping feed. The check runs in wp-admin.

== Description ==

The plugin reads published products and flags an empty title, a title over 150 characters, a missing GTIN and MPN, a price that is not positive, a missing image, and a missing link.

It does not call Google, Merchant Center, or any other service. A clean row is not an approval.

Before a WordPress.org submission, install it on a shop and raise "Tested up to" to the version you actually used. The contributor slug must be the WordPress.org account Jeff designates.

== Installation ==

1. Copy the folder `weadu-shopping-feed` into `wp-content/plugins/`.
2. Activate WooCommerce, then activate this plugin.
3. Open WooCommerce, then Shopping feed check.

== Changelog ==

= 1.0.0 =
* First local check.
