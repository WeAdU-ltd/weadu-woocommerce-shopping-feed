# WeAdU Shopping Feed Check (WooCommerce plugin)

A free WordPress plugin for WooCommerce that checks the product fields a Google Shopping feed uses (title, description, link, image, availability, price, brand, GTIN, condition) and lists, product by product, what to fix before you send a feed to Merchant Center.

The check runs in wp-admin. The plugin does not call Google or any other service.

The WordPress.org listing text is in [`weadu-shopping-feed/readme.txt`](weadu-shopping-feed/readme.txt).

## Features

- Reads published products, up to a limit you choose (200 by default, 500 at most).
- Lists each product with its findings (errors and warnings) and a suggested title when the brand is missing from the title.
- Optional UTM parameters: when `utm_source`, `utm_medium`, and `utm_campaign` are all set, they are added to the product links that are checked.
- CSV export of the result.
- Interface in English and French (follows the WordPress locale).
- Available to users with the `manage_woocommerce` capability.

### How product fields are read

| Feed field | Source in WooCommerce |
| --- | --- |
| `id` | SKU, or the product ID when the SKU is empty |
| `title` | Product name |
| `description` | Description, or the short description when it is empty |
| `link` | Product permalink (with UTM parameters if they are set) |
| `image_link` | Featured image |
| `availability` | `in_stock` or `out_of_stock` |
| `price` | Price returned by WooCommerce, with the store currency |
| `brand` | First `product_brand` term, then the `pa_brand` attribute, then the `_brand` custom field |
| `gtin` | WooCommerce "GTIN, UPC, EAN, or ISBN" field when available, then the `_gtin` or `_ean` custom field |
| `mpn` | `_mpn` custom field |
| `condition` | `_condition` custom field, `new` when empty |

Variations are not checked one by one: the plugin reads the main products returned by WooCommerce.

### What is checked

Errors: empty id, title, description, link, image, availability, or price; title over 150 characters; description over 5,000 characters; link or image not starting with `http://` or `https://`; price not greater than zero; GTIN with an invalid length or check digit; the same id used twice.

Warnings: title under 15 characters, all in capitals, or with promotional wording (for example "free shipping", "sale"); price without a currency code; empty brand; empty GTIN and MPN; empty or unknown condition.

A product with no finding is not an approval from Google: Merchant Center applies many more rules.

## Requirements

WordPress 6.2 or later, PHP 7.4 or later, WooCommerce 8.0 or later (as declared in the plugin header).

## Installation

1. Download the plugin ZIP from the latest release of this repository, or build it yourself: `zip -r weadu-shopping-feed.zip weadu-shopping-feed`.
2. In wp-admin, open **Plugins → Add New Plugin → Upload Plugin** and select the ZIP, or copy the `weadu-shopping-feed` folder into `wp-content/plugins/`.
3. Activate WooCommerce, then activate **WeAdU Shopping Feed Check**.
4. Open **WooCommerce → Shopping feed check**.

## Usage

1. Optional: enter `utm_source`, `utm_medium`, `utm_campaign`, and a product limit, then click **Save settings**. UTM values may contain letters, digits, and `. _ ~ -`, with no spaces.
2. Click **Run check**.
3. Read the table, or click **Download CSV**.

## Settings

| Option | Default | Notes |
| --- | --- | --- |
| `weadu_sf_source`, `weadu_sf_medium`, `weadu_sf_campaign` | empty | Added to the checked links only when all three are set. |
| `weadu_sf_limit` | 200 | Between 1 and 500. |

## Privacy

The plugin reads your products in your own wp-admin and shows the result there. It sends no data anywhere, calls no external service, and has no telemetry. It does not modify your products.

## Development

```sh
sh test.sh   # PHP syntax check, shared logic tests, and plugin tests (PHP and Node.js 20 or later)
```

`test/plugin_test.php` runs the plugin against small stand-ins for the WordPress and WooCommerce functions it uses. It does not replace a test on a real site.

## License

[MIT](LICENSE)

## About

Made by [WeAdU](https://weadu.com). WeAdU is not affiliated with WordPress, WooCommerce, or Google.
