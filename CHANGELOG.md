# Changelog — RA Promo Manager

Versions follow semver: PATCH = fixes, MINOR = new backward-compatible features, MAJOR = breaking changes (none yet).

---

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
