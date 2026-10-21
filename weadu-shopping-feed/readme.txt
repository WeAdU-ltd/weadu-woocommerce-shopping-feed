=== WeAdU Shopping Feed Check ===
Contributors: weadu
Tags: woocommerce, google shopping, product feed, gtin, merchant center
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Checks the WooCommerce product fields a Google Shopping feed uses and lists what to fix, product by product. Runs in wp-admin, sends nothing.

== Description ==

WeAdU Shopping Feed Check reads your published WooCommerce products and lists, for each one, the problems Google Shopping would likely report.

Errors:

* empty id, title, description, link, image, availability, or price;
* title over 150 characters, description over 5,000 characters;
* link or image not starting with http:// or https://;
* price not greater than zero;
* GTIN with an invalid length or check digit;
* the same id used twice.

Warnings:

* title under 15 characters, all in capitals, or with promotional wording such as "free shipping" or "sale";
* price without a currency code;
* empty brand;
* empty GTIN and MPN;
* empty or unknown condition.

It also suggests a title that starts with the brand when the brand is missing from the title, and exports the result as a CSV file. The interface is in English and French.

A product with no finding is not an approval from Google: Merchant Center applies many more rules.

= How product fields are read =

* id: SKU, or the product ID when the SKU is empty.
* description: description, or the short description when it is empty.
* GTIN: the WooCommerce "GTIN, UPC, EAN, or ISBN" field when available, otherwise the `_gtin` or `_ean` custom field.
* MPN: the `_mpn` custom field.
* brand: the first product brand (`product_brand` taxonomy), otherwise the `pa_brand` attribute, otherwise the `_brand` custom field.
* condition: the `_condition` custom field, `new` when it is empty.
* price: the price WooCommerce returns, with the store currency.
* availability: in stock or out of stock.

= Privacy =

The plugin reads your products in your own wp-admin and shows the result there. It does not call Google, Merchant Center, or any other service, sends no data, and has no telemetry. It does not modify your products. It stores one option: the product limit.

== Installation ==

1. Upload the `weadu-shopping-feed` folder to `wp-content/plugins/`, or install the ZIP from Plugins → Add New Plugin → Upload Plugin.
2. Activate WooCommerce, then activate WeAdU Shopping Feed Check.
3. Open WooCommerce → Shopping feed check.
4. Optional: enter a product limit (200 by default, 500 at most), then click Save settings.
5. Click Run check.

== Frequently Asked Questions ==

= Does it change my products or my feed? =

No. It only reads products and displays a report.

= Does it send my catalogue to Google or to WeAdU? =

No. Nothing leaves your site.

= Who can use it? =

Users who can manage WooCommerce (the `manage_woocommerce` capability).

= Are variations checked? =

No. The check reads the main products returned by WooCommerce, not each variation.

== Changelog ==

= 1.0.0 =
* First release: product check, CSV export, English and French interface.
