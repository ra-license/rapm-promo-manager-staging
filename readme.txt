RA Promo Manager
================

Validated, scheduled promotional assets for client WordPress sites — built to replace Soliloquy (unreliable, breaks on WP core updates and jQuery version conflicts) and to unify with NovaSlider (an in-house hero-carousel plugin, already live on kemperhomefurnishings.com) into one system.

== What this solves ==
Non-technical client staff upload promotional graphics that are often the wrong dimensions, the wrong file format, or too large to load quickly. This plugin makes that impossible: every image is checked against the exact pixel dimensions its placement needs before it's accepted, and automatically converted to WebP and compressed toward a file-size target — no one has to know what WebP is or how to make one. Only the crop/dimensions are ever a hard rejection (a wrong crop needs a human to fix, format doesn't).

**What isn't caught automatically:** whether the image already has sale text/pricing/dates baked into the pixels, which would visibly clash with the Headline/Subheadline/Button Text fields below it once both render together. Detecting text inside an image needs OCR — a paid cloud API or a server binary this plugin deliberately doesn't depend on, since it'd be an external cost and a point of failure across every client host. Instead, the Add/Edit Asset form has a live preview showing the image and text overlay together exactly as they'll appear live, specifically so this is easy to catch by eye before publishing.

== Getting help ==
**Promo Manager > Help & FAQ** is a plain-language walkthrough and Q&A written for non-technical users, open to anyone who can add assets. There's also a "Help & FAQ" button on the Add/Edit Asset form itself.

== Adding an asset ==
Use **Promo Manager > Add New Asset**. Don't use the native "Add New" screen under Promo Manager > All Assets — it's a bare title field on purpose (no image/format path exists there at all) and will redirect you to the real form automatically.

Fill in:
- **Kind** — Hero (the full-height carousel) or Fold Banner (a shorter strip meant to sit lower on the page, near the fold). Switches which image dimensions are required below.
- **Internal Name** — for your own reference in the admin list, not shown publicly.
- **Placement** — which `[rapm_hero placement="..."]` or `[rapm_fold_banner placement="..."]` this belongs to (matched separately per Kind, so a "default" hero and a "default" fold banner don't mix). Leave as "default" unless the site needs more than one of the same Kind (e.g. a homepage hero and a separate category-page hero).
- **Desktop/Mobile images** — upload whatever format/size you have; it's validated and converted automatically. A mismatch tells you the exact dimensions needed vs. what you uploaded.
- **Headline / Subheadline / Button Text** — real text rendered over the image, never part of the image file itself. This is what keeps it searchable and accessible, and lets you fix a typo without re-uploading anything.
- **Link** — a plain web address, a page/post on this site, or (if WooCommerce is active) a specific product, a category, a brand, search results for some words, or a hand-picked list of SKUs with automatic fill-in. For anything other than a plain web address, start typing a name and pick from the matches — no ID numbers needed.
- **Schedule** — start/end date-time. Works correctly even behind a full-page cache plugin (WP Rocket etc.) — the schedule is checked in each visitor's own browser, not baked into a cached page, so nothing "freezes."

== Displaying a carousel ==
`[rapm_hero]` — the full hero carousel, "default" placement.
`[rapm_fold_banner]` — the shorter fold-banner carousel, "default" placement.
`[rapm_hero placement="category-living-room"]` — a separate carousel scoped to just that placement (works the same for `[rapm_fold_banner]`).
An Elementor widget ("Promo Carousel," under the Promo Manager category) wraps either shortcode via its own Kind control.
`[rapm_curated_results]` — put this on one plain page, then set its address under Promo Manager > Settings > Curated Results Page. Every "hand-picked list" link on the site reuses this one page automatically.

== Image specs (defaults — adjustable under Promo Manager > Settings) ==
- Hero Desktop: 1920x600px, WebP, under 300KB
- Hero Mobile: 1080x1920px, WebP, under 300KB
- Fold Banner Desktop: 1920x300px, WebP, under 200KB
- Fold Banner Mobile: 1080x400px, WebP, under 200KB

The hero numbers were checked against real Core Web Vitals/LCP guidance, not picked arbitrarily. The fold banner numbers are a documented starting point, not an external standard — no established industry convention exists for this shape (checked directly) — adjust freely if a site's own data says otherwise. See CHANGELOG.md for the full research trail on both.

== Requirements ==
PHP with either the Imagick extension (preferred) or GD's WebP support, for automatic format conversion. If a host has neither, the plugin will clearly say so and ask for a pre-converted .webp file instead of failing silently.

== Roadmap ==
This is Phase 1 of a larger plan (asset core + hero carousel + fold banner). Planned next: a coupon-book scroll-snap display, a marquee/ticker display, and a calendar display — all reading from the same validated asset/slot/scheduling core built here, not a separate system.
