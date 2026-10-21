# WordPress.org listing texts (English)

The listing is generated from `weadu-shopping-feed/readme.txt`. Keep both in sync.

## Plugin name

WeAdU Shopping Feed Check

## Short description (150 characters max)

Checks the WooCommerce product fields a Google Shopping feed uses and lists what to fix, product by product. Runs in wp-admin, sends nothing.

## Tags (5 max)

woocommerce, google shopping, product feed, gtin, merchant center

## Description

WeAdU Shopping Feed Check reads your published WooCommerce products and lists, for each one, the problems Google Shopping would likely report.

Errors: empty id, title, description, link, image, availability, or price; title over 150 characters; description over 5,000 characters; link or image not starting with http:// or https://; price not greater than zero; GTIN with an invalid length or check digit; the same id used twice.

Warnings: title under 15 characters, all in capitals, or with promotional wording such as "free shipping" or "sale"; price without a currency code; empty brand; empty GTIN and MPN; empty or unknown condition.

It also suggests a title that starts with the brand when the brand is missing from the title, can add your UTM parameters to the links it checks, and exports the result as a CSV file. The interface is in English and French.

The plugin does not call Google, Merchant Center, or any other service, sends no data, and does not modify your products. A product with no finding is not an approval from Google.
