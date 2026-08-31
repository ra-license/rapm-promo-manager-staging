RA Promo Manager
================

Validated, scheduled promotional assets for client WordPress sites — built to replace Soliloquy (unreliable, breaks on WP core updates and jQuery version conflicts) and to unify with NovaSlider (an in-house hero-carousel plugin, already live on kemperhomefurnishings.com) into one system.

== What this solves ==
Non-technical client staff upload promotional graphics that are often the wrong dimensions, the wrong file format, or too large to load quickly. This plugin makes that impossible: every image is checked against the exact pixel dimensions its placement needs before it's accepted, and automatically converted to WebP and compressed toward a file-size target — no one has to know what WebP is or how to make one. Only the crop/dimensions are ever a hard rejection (a wrong crop needs a human to fix, format doesn't).

== Adding an asset ==
Use **Promo Manager > Add New Asset**. Don't use the native "Add New" screen under Promo Manager > All Assets — it's a bare title field on purpose (no image/format path exists there at all) and will redirect you to the real form automatically.

Fill in:
- **Internal Name** — for your own reference in the admin list, not shown publicly.
- **Placement** — which `[rapm_hero placement="..."]` this belongs to. Leave as "default" unless the site needs more than one independent hero carousel (e.g. a homepage one and a separate category-page one).
- **Desktop/Mobile images** — upload whatever format/size you have; it's validated and converted automatically. A mismatch tells you the exact dimensions needed vs. what you uploaded.
- **Headline / Subheadline / Button Text** — real text rendered over the image, never part of the image file itself. This is what keeps it searchable and accessible, and lets you fix a typo without re-uploading anything.
- **Link** — a URL, a page/post on this site, or (if WooCommerce is active) a product or category.
- **Schedule** — start/end date-time. Works correctly even behind a full-page cache plugin (WP Rocket etc.) — the schedule is checked in each visitor's own browser, not baked into a cached page, so nothing "freezes."

== Displaying the carousel ==
`[rapm_hero]` — the "default" placement.
`[rapm_hero placement="category-living-room"]` — a separate carousel scoped to just that placement.
An Elementor widget ("Promo Hero Carousel," under the Promo Manager category) wraps the same shortcode.

== Image specs (defaults — adjustable under Promo Manager > Settings) ==
- Hero Desktop: 1920x600px, WebP, under 300KB
- Hero Mobile: 1080x1920px, WebP, under 300KB

Both numbers were checked against real Core Web Vitals/LCP guidance, not picked arbitrarily — see CHANGELOG.md.

== Requirements ==
PHP with either the Imagick extension (preferred) or GD's WebP support, for automatic format conversion. If a host has neither, the plugin will clearly say so and ask for a pre-converted .webp file instead of failing silently.

== Roadmap ==
This is Phase 1 of a larger plan (asset core + hero carousel). Planned next: a coupon-book scroll-snap display, a marquee/ticker display, and a calendar display — all reading from the same validated asset/slot/scheduling core built here, not a separate system.
