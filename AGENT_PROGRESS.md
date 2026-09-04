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

## 2026-09-04 — task 1292
Done: contributie-workflow (PLAN.md §2.5). New `idr_proposal` CPT (plugin/idr-discography/
idr-proposals.php, not public) with 3 custom statuses (idr_pending/idr_accepted/idr_rejected),
admin list with doel/veld/indiener columns, pending-count menu bubble. Front-end "Vul aan of
corrigeer" formulier (theme/idr-react/parts-contribute.php) op elke release/song-pagina, geen
account nodig, werkt zonder JS (admin-post.php + redirect). Anti-spam: nonce + honeypot (stil
genegeerd, geen bot-signaal) + rate-limit (max 5/uur per IP, transient). Redactie beoordeelt in
wp-admin: meta box toont doel + diff (huidig vs voorgesteld) + bron + indiener; accepteren past
alleen expliciet structured velden (jaar/label/catalogusnummer/taal) automatisch toe op de
doelpost, nooit post_content — vrije toelichting (songtekst/overig) blijft een bewuste
handmatige redactiestap voor accepteren, om te voorkomen dat een kort "voorgestelde
waarde"-veld per ongeluk legacy-HTML overschrijft. Afwijzen archiveert met optionele reden.
EN/DE-labels via __stm(), vertalingen geseed via tools/deploy.py (zelfde patroon als 1297).
Deployed via `tools/deploy.py` en fast-forwarded `main` direct (repo's no-PR-conventie) —
gebruikte het `martiendejong` gh-account voor de push, `scp-jengo` authenticeert wel maar
heeft geen schrijftoegang tot deze repo (ondanks dat eerdere commits met een scp-jengo-e-mail
zijn geauteurd).
Verified live (wp-admin login als idr-admin via curl, geen browser nodig): formulier rendert
op een echte release- en songpagina in NL/EN/DE met correcte velden per CPT; een echte POST
zonder honeypot komt in de wachtrij terecht, met honeypot ingevuld NIET (0 extra posts); de
6e voorstel binnen het uur wordt correct geweigerd (rate-limit); de meta box toont de juiste
diff/bron/indiener; accepteren past de voorgestelde catalogusnummer-waarde automatisch toe op
de echte doelpost (bevestigd op de live pagina) en toont een succesmelding; afwijzen archiveert
met de ingevoerde reden en verbergt de actieknoppen. `php -l` clean op alle 3 gewijzigde/nieuwe
PHP-bestanden. Onderweg een echte bug gevonden en gefixt: het standaard "Alle"-lijstscherm in
wp-admin toonde "Geen berichten gevonden" ondanks een correct tellende (2)-badge, omdat
WP_Query's ingebouwde default-statusset geen custom statussen bevat — opgelost met een
pre_get_posts-filter. Alle testdata (5 test-voorstellen, 1 tijdelijke catalogusnummer-waarde)
na verificatie opgeruimd; `wp-json/idr/v1/status` bevestigt 60/161/2/24/6 ongewijzigd.
Not verified: een echte browser-sessie (geen Playwright/MCP browser beschikbaar op deze host;
alle verificatie via curl + cookie-auth), en de exacte visuele opmaak van het formulier (CSS
niet in een browser gerenderd, alleen tegen het bestaande tokenbudget geschreven).
Left: PLAN.md §5 noemt "wie er naast Geert moderatierechten krijgt" nog als open punt — dit
voorstel gebruikt bewust de standaard `edit_others_posts`-capability (Editor/Admin) zodat die
beslissing later zonder codewijziging aangepast kan worden.
