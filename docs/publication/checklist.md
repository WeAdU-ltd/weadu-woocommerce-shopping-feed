# Before publication

Nothing in this folder may be posted before every box below is ticked.

- [ ] Pull requests #1 and #2 reviewed independently and merged.
- [ ] `sh test.sh` green on `main` (and the GitHub Actions workflow enabled: copy `ci/github-actions-test.yml` to `.github/workflows/test.yml` with a token that has the `workflow` scope; it runs the tests on PHP 7.4, 8.1, and 8.3).
- [ ] End-to-end test on a real WordPress site with a current WooCommerce and real products: activate, configure, run the check, download the CSV. The current tests use stand-ins for WordPress and WooCommerce.
- [ ] `Tested up to` in `readme.txt` set to the WordPress version actually tested (7.1.3, so `7.1`).
- [ ] `Contributors: weadu` replaced by the WordPress.org username that will own the plugin.
- [ ] Plugin Check (https://wordpress.org/plugins/plugin-check/) run on the release ZIP and its errors fixed. Points it is likely to raise: interface strings use a custom `weadu_sf_t()` helper instead of `__()` with the text domain, and there is no `uninstall.php` to delete the four `weadu_sf_*` options.
- [ ] Known issue fixed or accepted: on PHP 8.4, `fputcsv()` in `includes/FeedAudit.php` raises a deprecation notice because the `$escape` argument is not passed (reported by `test/plugin_test.php`).
- [ ] Plugin submitted on WordPress.org by the owner of the account and approved.
- [ ] Link sent to the person who posts, so `LINK` can be replaced.
