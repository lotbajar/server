=== HTML File Browser ===
Contributors:      yourname
Tags:              static, html, file-browser, dist
Requires at least: 5.0
Tested up to:      6.5
Stable tag:        1.0.0
License:           GPL-2.0+

Browse and serve static HTML files from the plugin's dist/ folder.

== Description ==

HTML File Browser lets you drop plain .html files into the plugin's `dist/` folder
and serve them at clean WordPress URLs — no page/post needed.

**Features**
* Listing page with live search, file size, and last-modified date
* Each file served at `/html-pages/<filename>` (no .html extension in URL)
* "Back to listing" bar injected automatically into every served file
* Custom 404 page for missing files
* WordPress Admin panel showing all dist/ files with quick-view links
* Path-traversal protection (only alphanumeric + dash/underscore filenames)

**URL structure**
| What                 | URL                               |
|----------------------|-----------------------------------|
| File listing         | https://yoursite.com/html-pages/  |
| Serve a file         | https://yoursite.com/html-pages/my-file |

== Installation ==

1. Upload the `html-file-browser` folder to `/wp-content/plugins/`.
2. Activate through *Plugins* in WordPress admin.
3. Go to *Settings → Permalinks* and click **Save** (flushes rewrite rules).
4. Drop your `.html` files into `wp-content/plugins/html-file-browser/dist/`.
5. Visit `https://yoursite.com/html-pages/` to see the listing.

== Frequently Asked Questions ==

= I get a 404 on /html-pages/ =
Go to Settings → Permalinks and click Save Changes. This flushes the rewrite rules.

= Can I change the /html-pages/ URL prefix? =
Yes — edit the `HFB_SLUG` constant near the top of `html-file-browser.php`.

= Are subdirectories supported? =
Currently only .html files directly inside dist/ are served. Nested folders are not.

== Changelog ==

= 1.0.0 =
* Initial release
