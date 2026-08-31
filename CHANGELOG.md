# Changelog — RA Promo Manager

Versions follow semver: PATCH = fixes, MINOR = new backward-compatible features, MAJOR = breaking changes (none yet).

---

## 1.2.0

**Feature: plain-language guided form for non-technical uploaders.** Prompted directly by the concern that the people actually using this form day-to-day may have no idea what "image size," "file type," or web jargon in general means, and previous copy leaned on exactly that (e.g. explaining live text via "keeps it readable to... AI answer tools").

- The Headline/Subheadline/Button Text fields are no longer just described in prose as "the alternative to baking text into the image" — there's now an explicit, plain-language either/or choice at the top of that section ("Does your picture already show the price, sale, or date on it?" — Yes/No radio buttons), and choosing "Yes" hides the text fields entirely rather than leaving someone to infer they should skip them.
- **Fix, found while wiring this up:** the hidden-but-still-present text fields would have been submitted along with the form regardless of the radio choice (CSS `display:none` doesn't stop a value from being submitted) — `handle_save()` now explicitly force-clears Headline/Subhead/CTA server-side whenever "already on the picture" is chosen, so a stale value can't sneak through.
- **Fix, found in the same pass:** the Button Text field's "Shop Now" suggestion was previously a pre-filled *value*, not a placeholder hint — meaning re-editing an asset where it had been deliberately left blank would silently re-populate "Shop Now" and could get re-saved on the next edit. Now a real `placeholder`, only ever saved if actually typed.
- Rewrote the jargon-heavy copy throughout the rest of the form into plain language: "Kind" → "Type of Promotion," "Links To"/"Link Target" → "Send visitors to..."/"Address / ID," "Schedule" → "When It Should Show," dimension requirements now explain what "pixels" means instead of assuming it, and all mentions of WebP/SEO/screen-readers/full-page-caching removed from user-facing copy (kept only in code comments, where they belong).

## 1.1.2

**Fixes, prompted by verifying that live text is genuinely optional (it is — Headline/Subhead/Button Text have never been required fields, server- or client-side):**
- An image-only slide (no headline/subhead/CTA at all) previously still rendered the `.rapm-slide-copy` gradient overlay wrapper unconditionally, subtly darkening the bottom of an otherwise "clean" image. Now that wrapper only renders when there's actually text to show.
- The desktop image, on the other hand, effectively *was* required — without one, `render_slide()` already silently skipped the asset entirely (nothing to render), but nothing told the person saving it that would happen. Added a clear error instead: *"Please upload a desktop image — it's required for this asset to actually display anywhere."* Plus client-side `required` on that file input for new assets, for immediate feedback.

## 1.1.1

**Feature: live WYSIWYG preview + text-clash warning on the Add/Edit Asset form.** Addresses a real gap: nothing previously stopped someone from uploading an image with sale text already baked into the pixels *and* separately filling in Headline/Subheadline/Button Text, producing visibly overlapping/duplicate text on the live page. Automatically detecting text inside an image would require OCR (a paid cloud vision API or a server binary rarely available on typical WordPress hosting) — a real external dependency and cost this plugin deliberately doesn't take on. Instead: the form now shows a live preview (image + text overlay rendered together, using the exact same CSS the live carousel uses) that updates as you type or choose a file, so whoever's uploading can see and fix a clash themselves before it ever publishes — plus an explicit on-screen warning next to the image fields. Loads `rapm-hero.css` only on this one admin screen.

## 1.1.0

**Feature: Fold Banner, a second display "kind" alongside the Hero.** Prompted by the user's own research suggesting a shorter banner positioned near the fold can outperform a full hero slider for engagement — checked this against real published research before building on it: the specific claim isn't directly backed by any citable study (no A/B test isolating banner position on CTR was found), but it's a plausible synthesis of several real, adjacent findings (NN/g's banner-blindness research ties ad-avoidance specifically to *position*, not just style; Chartbeat's ~2B-pageview analysis found engagement peaking just above/below the fold rather than at the very top; tracked carousel data shows real CTR problems). Rather than picking one placement as universally correct, both are now supported so a client's own data can decide.

- New `fold_banner` kind: 1920×300 desktop / 1080×400 mobile, both under 200KB (no established industry dimension convention exists for this shape — checked directly — so these are a documented starting point, adjustable per-site, not an external standard).
- `RAPM_Slots::kinds()` maps each kind to its desktop/mobile slot pair — the one place a third kind would need registering later.
- The "Add New Asset" form gained a **Kind** selector (Hero vs. Fold Banner) that switches which image dimensions are required, before any file is chosen.
- New `[rapm_fold_banner placement="..."]` shortcode, sharing the exact same carousel/scheduling engine as `[rapm_hero]` (`RAPM_Hero_Carousel::render()`, parameterized by kind) rather than a duplicated implementation. The Elementor widget gained a matching Kind control.
- Carousel container sizing switched from a fixed pixel height to CSS `aspect-ratio` driven by the active kind's slot dimensions — this also fixes the hero carousel now correctly sizing itself instead of assuming one fixed height, and means a future third kind needs no CSS changes at all.

## 1.0.0

Initial build — Phase 1 of the unified promotional display plugin (see the approved plan for full context). Consolidates two previously-separate things: Soliloquy (a third-party slider with confirmed live bugs — breaks on WP core updates, jQuery-version conflicts, unreliable scheduling, per its own WordPress.org support forum) and NovaSlider (an in-house Swiper.js plugin already live on kemperhomefurnishings.com, with zero image validation).

**Core: validated asset pipeline.**
- New `rapm_asset` custom post type. Real asset creation always goes through the validated "Add New Asset" admin form (`RAPM_Upload_Handler`), not the native post editor — the CPT is registered with no `thumbnail`/`editor` support, so there's no unvalidated image-upload path to begin with.
- Every uploaded image is checked against its slot's required pixel dimensions (± a small tolerance — client exports are rarely pixel-perfect) *before* being accepted; a mismatch is rejected with the exact numbers needed vs. what was uploaded, not a generic error.
- Non-WebP uploads are auto-converted to WebP and compressed toward the slot's file-size target server-side (`RAPM_Webp_Converter`: Imagick preferred, GD fallback, clear admin-facing failure if neither is available on the host) — removes the need for anyone to know what WebP is or how to make one.
- Default slots: Hero Desktop (1920×600, under 300KB) and Hero Mobile (1080×1920, under 300KB), both overridable per-site under Promo Manager > Settings without code changes. New slots can be registered via the `rapm_slots` filter.
- Promotional copy (headline, subheadline, CTA text) is always stored and rendered as real HTML text layered over the image — never baked into the image file. This is what keeps it crawlable, screen-reader accessible (WCAG 1.4.5), and editable without a re-upload.

**Display: hero carousel** (`[rapm_hero placement="default"]`, plus an Elementor widget).
- Built by evolving NovaSlider's proven approach rather than starting over: still Swiper.js, still schedules client-side.
- **Scheduling is deliberately client-side, not a server-render-time filter.** Every asset renders with `data-rapm-start`/`data-rapm-end` regardless of the current time; a shared script (`assets/js/rapm-schedule.js`) decides what's actually active in the visitor's own browser and re-checks every 60 seconds. This is what makes scheduling survive a full-page cache plugin (WP Rocket etc.) instead of "freezing" whatever was active when the cache was last built — the exact failure mode this plugin replaces.
- Multiple independent carousels per site via the `placement` field/attribute (e.g. a homepage hero and a separate category-page hero).
- Carries forward two real production fixes already proven in NovaSlider: conditional asset loading (`should_load_assets()` — only enqueues Swiper on pages that actually use the shortcode, with an Elementor-shortcode-widget detection workaround and a `rapm_force_load_ids` filter escape hatch) and a `rocket_delay_js_exclusions` filter so WP Rocket's "Delay JavaScript Execution" doesn't leave the carousel static.
- Emits `ImageObject` JSON-LD per rendered slide for basic SEO/AEO crawlability.
- Links to a plain URL, a WordPress post/page, or (if WooCommerce is active) a WooCommerce product/category — `RAPM_Destination` centralizes resolving all four so the renderer never branches on link type itself.

**Not in this phase** (see the plan): coupon-book scroll-snap display, marquee/ticker display, calendar display. All three are designed to read from the same `rapm_asset`/slot/scheduling core being built here, not a separate system.
