# BCO vCard Plugin: Scope (v1.0.0)

A WordPress plugin that lets a client manage employee contact records and serve them as downloadable .vcf files at clean public URLs. Those URLs are designed to be encoded into print QR codes (business cards). The plugin includes built-in QR code generation (SVG primary, PNG fallback) and lightweight scan tracking.

Initial deployment client: Hyperion Automation (https://hyperionautomation.com/). Plugin is built to be reusable across other BCO clients via the `bco-` namespace.

## Plugin identity

| Property | Value |
|---|---|
| Plugin name | BCO vCard |
| Plugin slug (folder) | `bco-vcard` |
| PHP namespace | `BCO\vCard` |
| Text domain | `bco-vcard` |
| Top-level admin menu | "vCards" (contact card icon) |
| Initial version | 1.0.0 |
| Minimum WP | 6.4 |
| Minimum PHP | 8.1 |

## Custom post type

Single CPT: `bco_vcard`. One post per employee. The post slug becomes the public URL.

### Per-employee fields (edit screen)

| Field | Required | Notes |
|---|---|---|
| First name | Yes | Used in `N` and `FN` |
| Last name | Yes | Used in `N` and `FN` |
| Job title | No | vCard `TITLE` |
| Department | No | Becomes second component of `ORG` (e.g. `ORG:Hyperion Automation;Engineering`) |
| Mobile phone | No | Normalized to E.164 on save; output as `TEL;TYPE=CELL,VOICE` |
| Work phone | No | Normalized to E.164 on save; output as `TEL;TYPE=WORK,VOICE`. Falls back to org main phone if empty |
| Email | No | Output as `EMAIL;TYPE=WORK` |
| Personal URL | No | Optional, future-proofing for bio pages. Adds a second `URL` line when set |
| LinkedIn URL | No | Adds a `URL` line. Validate the value starts with `https://linkedin.com/` or `https://www.linkedin.com/` |
| Active toggle | Yes (default: on) | When off, public URL returns 404 |

The post Title field is auto-populated as "First Last" on save (read-only or auto-synced; do not require manual entry).

### Org-wide defaults (Settings → BCO vCard)

These apply to every vCard unless overridden on the individual record.

| Setting | Default for HYP |
|---|---|
| Organization name | Hyperion Automation |
| Organization website | https://hyperionautomation.com/ |
| Work street address | (configurable) |
| Work city | (configurable) |
| Work state | (configurable) |
| Work zip | (configurable) |
| Work country | (configurable) |
| Main work phone | (configurable; fallback for employees without a work phone) |
| URL slug base | `v` (editable; the plugin should support changing this and flush rewrite rules accordingly) |
| Default error correction level | M (15%) |
| Scan tracking | Enabled (toggle in settings) |

## vCard output format

Output is vCard 3.0 (better universal device support than 4.0). Empty fields must be omitted entirely; do not output blank `FIELD:` lines.

### Sample output

```
BEGIN:VCARD
VERSION:3.0
N:Smith;Jane;;;
FN:Jane Smith
ORG:Hyperion Automation;Engineering
TITLE:VP of Engineering
TEL;TYPE=CELL,VOICE:+16165551234
TEL;TYPE=WORK,VOICE:+16165554321
EMAIL;TYPE=WORK:jane@hyperionautomation.com
URL:https://hyperionautomation.com/
URL:https://linkedin.com/in/jane-smith
ADR;TYPE=WORK:;;123 Example St;Holland;MI;49423;USA
REV:2026-05-04T12:00:00Z
END:VCARD
```

### Format requirements

- vCard escaping is required for: `;`, `,`, `\n`, `\` (backslash). Apply to every text field before output.
- Phone numbers normalized to E.164 (`+16165551234`) on save. Strip formatting, validate, store normalized.
- `REV` field uses the post's modified timestamp in UTC ISO 8601.
- Line endings: CRLF (`\r\n`) per RFC 6350.
- File encoding: UTF-8.
- Folding (line breaks for lines over 75 chars) per RFC 6350 if needed; `chillerlan` does not handle this, implement in the formatter.

## Public URL behavior

### URL pattern

```
https://hyperionautomation.com/v/{slug}
```

The `.vcf` extension is intentionally not part of the URL. Phones recognize the file type via the `Content-Type` header, not the URL extension. Shorter URL = sparser QR code = more reliable scanning at small print sizes.

### Response headers

```
Content-Type: text/vcard; charset=utf-8
Content-Disposition: attachment; filename="firstname-lastname.vcf"
Cache-Control: no-cache, no-store, must-revalidate
Pragma: no-cache
```

### Behavior by platform

- iOS Safari: opens the Contacts "Add Contact" sheet immediately on download.
- Android (Chrome): downloads the file; user taps to import to Contacts.
- Desktop browsers: standard file download.
- Both with and without trailing slash should work.
- HEAD requests should return the same headers as GET, with no body and no scan-count increment.

### 404 cases

- Slug does not match any `bco_vcard` post: return WP's standard 404.
- Matching post exists but Active toggle is off: return 404 (not a download of an empty or stub file).
- Matching post exists but is in trash or draft: return 404.

## Implementation: rewrite rules

Register a custom rewrite rule:

```
^v/([^/]+)/?$ → index.php?bco_vcard_slug=$matches[1]
```

Hook `template_redirect` to detect the query var, look up the post, build the vCard, and stream it. Exit before WP renders a template.

Flush rewrite rules on plugin activation and deactivation. Also flush when the URL slug base setting is changed.

## Admin UX

### vCards list view (custom columns)

| Column | Content |
|---|---|
| Name | Post title (First Last) |
| Title | Job title |
| URL | `/v/jane-smith` with copy-to-clipboard icon |
| QR | Icon button that opens a modal with QR preview and download buttons |
| Status | Active / Inactive badge |
| Scans | Integer count (sortable; only shown if scan tracking is enabled) |
| Last scan | Relative time ("3 days ago") |
| Modified | WP standard |

### vCard edit screen

- Standard WP title field repurposed as "Full Name" (auto-built from First + Last; consider hiding the default title input and showing a synthesized read-only line, or syncing on save).
- Custom meta box "Contact Details" with all per-employee fields.
- Sidebar meta box "QR Code":
  - Live SVG preview of the QR code (renders the current saved slug; greyed out with "save to generate" message if post is new).
  - Button: Download SVG (primary).
  - Button: Download PNG (1024 x 1024).
  - Button: Copy URL.
  - Below buttons, a small print specs block:
    > Print specs: minimum 0.8 in (20 mm). Recommended: 1 in (25 mm) for business cards. Do not crop the white border (quiet zone). Use SVG for any print application.
- Sidebar meta box "Stats" (only if scan tracking enabled):
  - Total scans.
  - Last scan timestamp.

### Settings page

Single screen at Settings → BCO vCard. Sections:

1. Organization defaults (all org-wide fields above).
2. URL configuration (slug base, currently `v`).
3. QR code defaults (error correction level dropdown).
4. Scan tracking (enable/disable toggle, info text explaining what is and is not tracked).

## QR code generation

### Library

`chillerlan/php-qrcode` (MIT licensed). Install via Composer; commit the `vendor/` directory to the plugin (standard practice for distributable WP plugins so installation does not require Composer on the target server).

### Output specs

| Spec | Value |
|---|---|
| Primary format | SVG |
| Fallback format | PNG, 1024 x 1024 px |
| Error correction level | M (15%), configurable in settings |
| Quiet zone | 4 modules (always; do not let users disable) |
| Module color | `#000000` |
| Background color | `#FFFFFF` |
| Logo overlay | None (intentionally; would force ECC up to H and densify the code) |

The generated SVG should be self-contained (no external references), use absolute units sized for the viewBox, and be valid for direct print placement.

## Scan tracking

When scan tracking is enabled, a hit on the public `/v/{slug}` URL increments a counter and updates a timestamp.

### Stored data (per vCard, as post meta)

- `_bco_vcard_scan_count`: integer
- `_bco_vcard_last_scanned`: MySQL datetime in UTC

### Not stored

No IP, no user agent, no session, no geolocation, no scan history. Each new scan overwrites the previous "last scanned" value.

### Bot filtering

Before incrementing the counter, check the `User-Agent` header against a list of common bots and skip the increment if matched. Recommended starting list:

- googlebot
- bingbot
- duckduckbot
- baiduspider
- yandexbot
- slurp (Yahoo)
- facebookexternalhit
- twitterbot
- linkedinbot
- slackbot
- discordbot
- whatsapp
- telegrambot
- skypeuripreview
- applebot
- uptimerobot
- pingdom
- gtmetrix
- semrushbot
- ahrefsbot
- mj12bot
- dotbot

Implement as an array filter that bot matchers can extend via a filter hook (`bco_vcard_bot_user_agents`).

The actual file delivery is not affected by bot filtering; only the counter increment is skipped.

### Display

- List view column "Scans" (sortable).
- Edit screen "Stats" sidebar box.
- No data export UI in v1.0.0 (manageable via SQL or a future feature).

## File structure

```
bco-vcard/
├── bco-vcard.php                   # Main plugin file with header, bootstraps autoloader
├── composer.json
├── composer.lock
├── readme.txt                       # WP plugin readme format
├── README.md                        # Developer-facing
├── uninstall.php                    # Cleanup logic
├── languages/
│   └── bco-vcard.pot
├── src/
│   ├── Plugin.php                   # Main plugin class, hooks
│   ├── PostType.php                 # CPT registration
│   ├── Fields.php                   # Meta box rendering and save
│   ├── Settings.php                 # Settings page and option storage
│   ├── Rewrite.php                  # Rewrite rules and template_redirect handler
│   ├── VCardFormatter.php           # vCard 3.0 string builder
│   ├── QrGenerator.php              # Wrapper around chillerlan
│   ├── ScanTracker.php              # Scan counting and bot filtering
│   ├── AdminColumns.php             # List view columns
│   ├── PhoneNormalizer.php          # E.164 normalization
│   └── Helpers.php                  # vCard escaping, slug helpers
├── assets/
│   ├── css/admin.css
│   ├── js/admin.js                  # QR modal, copy-URL button
│   └── images/
└── vendor/                          # Committed; chillerlan and dependencies
```

## Edge cases to handle

1. Slug collision on rename: WP auto-appends `-2`, `-3`, etc. Default behavior is fine; verify it works for our CPT.
2. Special characters in names (apostrophes, accents, semicolons): full vCard escaping.
3. Trailing slash and no trailing slash both work via the rewrite rule.
4. HEAD request support (some scanners pre-fetch): return headers, no body, do not increment scan count.
5. Flush rewrite rules on activation, deactivation, and slug-base setting change.
6. Long names or titles that would push a vCard line past 75 chars: implement RFC 6350 line folding in the formatter.
7. Empty optional fields: omit the line entirely, do not output `TEL;TYPE=CELL,VOICE:` with no value.
8. Save behavior: if a post has no first or last name, prevent publish (admin notice).
9. Multisite: out of scope for v1.0.0 (single-site only).

## Activation, deactivation, uninstall

### Activation
- Register CPT (so flush works against it).
- Flush rewrite rules.
- Set default options if not present.

### Deactivation
- Flush rewrite rules.
- Do NOT delete data.

### Uninstall (`uninstall.php`)
- Setting in plugin settings: "On uninstall, delete all data" (default: off).
- If on: delete all `bco_vcard` posts, all `_bco_vcard_*` post meta, all `bco_vcard_*` options.
- If off: leave everything in place.

## Out of scope for v1.0.0

These are explicitly deferred:

- Photo / image support (vCard `PHOTO` field).
- Logo overlay on QR codes.
- Scan tracking time series / sparkline (only count and last-scan in v1.0.0).
- Bulk QR export (all employees in one PDF or zip).
- vCard 4.0 output.
- Multisite.
- Front-end public listing of employees.
- API access tokens for programmatic vCard updates.

## Build sequence (suggested order for Claude Code)

1. Plugin scaffold: main file header, autoloader, Plugin class, activation/deactivation hooks.
2. CPT registration with admin menu and icon.
3. Custom fields meta box (no QR yet).
4. Settings page with org defaults.
5. Phone normalizer.
6. vCard formatter (test with hardcoded data first).
7. Rewrite rule and template_redirect handler (serve the .vcf).
8. Test the public URL end to end before adding QR.
9. Composer install chillerlan/php-qrcode; commit vendor.
10. QR generator wrapper.
11. QR sidebar meta box on edit screen (preview + downloads).
12. Admin list columns including URL and QR.
13. Scan tracker with bot filter.
14. Stats display.
15. Uninstall cleanup.
16. Polish: admin CSS, copy-URL JS, modal styling.
17. readme.txt and README.md.

## Testing checklist

- [ ] Create a vCard, save, visit `/v/{slug}`, confirm .vcf downloads.
- [ ] Test on iOS Safari (should open Add Contact sheet).
- [ ] Test on Android Chrome (should download and open).
- [ ] Test desktop Chrome, Firefox, Safari.
- [ ] Toggle Active off, confirm URL returns 404.
- [ ] Test slug rename, confirm old slug 404s and new slug works.
- [ ] Test special characters in name (e.g. O'Brien, José, name with semicolon).
- [ ] Test empty optional fields produce no blank lines.
- [ ] Test department empty: `ORG:Hyperion Automation` not `ORG:Hyperion Automation;`.
- [ ] Test E.164 normalization with various input formats.
- [ ] Download SVG, open in browser and Illustrator, confirm clean vector.
- [ ] Print SVG at 0.8 in, 1 in, 1.5 in; confirm scanning at each size.
- [ ] HEAD request returns headers without body, no scan count increment.
- [ ] Bot user agent does not increment scan count.
- [ ] Real scan from phone increments scan count.
- [ ] Settings: change URL slug base, confirm rewrite rules flush and new URL works.
- [ ] Uninstall with "delete data" on: confirm clean removal.
- [ ] Uninstall with "delete data" off: confirm data persists for reactivation.
