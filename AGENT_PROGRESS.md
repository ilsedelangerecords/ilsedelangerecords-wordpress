# Agent Progress

## 2026-09-04 — task 1297
Done: vendored Simple Translation Manager v1.3.1 (`plugin/simple-translation-manager`),
configured NL(default)/EN/DE, wired the language switcher + `<html lang>` into the theme,
wrapped ~20 UI strings (nav, footer, hero, song/release labels) in `__stm()`/`_e_stm()` and
seeded their EN/DE translations. Added `_idr_variant_of` to the datamodel (idr-discography)
with an admin meta box for reciprocal DE/NL song-variant linking and a taalbadge cross-link
section on the song template. Added the "STM-les" stale-translation safety net: a
`before_delete_post` hook plus `wp idr clean-stale-translations` for a manual sweep, since
`wp_stm_post_translations` carries no source hash. Deployed via `tools/deploy.py` (fixed the
vault lookup to be portable — no `E:\` on this host) and fast-forwarded `main` directly
(this repo's established no-PR convention).
Verified live: `/`, `/en/`, `/de/` all render with the correct `<html lang>`, translated nav/
footer/hero text, and hreflang alternate tags; `wp-json/idr/v1/status` counts unchanged
(60/161/2/24/6, no data loss); `stm/v1` REST namespace registered; a real song detail page
renders clean with no PHP errors/warnings. `php -l` clean on all 13 changed/added PHP files.
Not verified: the `wp idr clean-stale-translations` CLI command's actual execution (no
SSH/WP-CLI access to this shared host, code-reviewed + syntax-checked only) and the admin
post-editor/meta-box UI in a real browser session (no wp-admin login available here).
Left: zero DE-language song variants exist in the currently imported archive — verified via
a full-corpus text scan of all 161 idr_song posts' content (only 1 spurious false-positive
match, no real German text found anywhere). The linking infrastructure (`_idr_variant_of`,
meta box, reciprocal linking, taalbadge cross-links) is complete and ready, but there is
nothing real to link yet. The astro/migration source repos PLAN.md references as import
sources (`E:\projects\ilsedelange-records-astro`, `E:\projects\idr-migration`) aren't
reachable from this host to check further — needs either restored access to those repos or
a manual curation pass identifying which archive pages are true German variants.
