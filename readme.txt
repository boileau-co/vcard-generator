=== vCard Generator ===
Contributors: boileaucreativeoperations
Tags: vcard, contact, qr code, business card, vcf
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage employee contact records and serve them as downloadable .vcf files at clean public URLs, with built-in QR code generation and scan tracking.

== Description ==

vCard Generator lets you manage employee contact records in WordPress and serve them as downloadable vCard (.vcf) files at short, clean URLs designed for print QR codes on business cards.

**Features:**

* Custom post type with per-employee contact fields (name, title, department, phone, email, LinkedIn, and more)
* vCard 3.0 output with correct RFC 6350 formatting, CRLF line endings, and line folding
* Clean public URL pattern: `/v/{slug}` (no .vcf extension needed — phones recognize the file type by Content-Type header)
* QR code generation (SVG primary, PNG 1024×1024 fallback) via chillerlan/php-qrcode
* QR code preview and download in the edit screen sidebar
* Scan tracking: counts hits per vCard, with bot filtering and no personal data stored
* Org-wide defaults (name, address, phone, website) applied to every vCard
* Active toggle: inactive vCards return 404
* Phone number normalization to E.164 format on save
* Settings page with URL slug base, QR error correction level, scan tracking toggle, and uninstall data cleanup option

== Installation ==

1. Upload the `vcard-generator` folder to `/wp-content/plugins/`.
2. Run `composer install --no-dev` inside the plugin folder (requires PHP 8.1+ and Composer).
3. Activate the plugin in **Plugins → Installed Plugins**.
4. Go to **vCards → Settings** and configure your organization defaults.
5. Add your first vCard under **vCards → Add New**.

== Frequently Asked Questions ==

= Why is the URL `/v/slug` instead of `/v/slug.vcf`? =

Shorter URLs produce less dense QR codes, which scan more reliably at small print sizes. Phones identify the file type from the `Content-Type: text/vcard` header, not the URL extension.

= Do I need Composer? =

Yes, to install the QR code library. Run `composer install --no-dev` once after uploading the plugin. The vendor directory should then be committed with the plugin for distribution so target servers do not need Composer.

= What data does scan tracking store? =

Only a total scan count and a "last scanned" timestamp per vCard. No IP address, user agent, geolocation, or session data is stored.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
