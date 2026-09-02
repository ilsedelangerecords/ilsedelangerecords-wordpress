# Plan: ilsedelangerecords.nl op WordPress met React-theme en Simple Translation Manager

**Datum:** 2026-09-02 · **Besluit:** WordPress + eigen React-theme + eigen plugin, vertalingen via [Simple Translation Manager](https://github.com/martiendejong/simple-translation-manager) (STM). Vervangt de Astro-route (ilsedelange-records-astro PR #1); de migratiedata uit die PR blijft de importbron.

## 1. Wat er ligt (analyse 02-09-2026)

| Bron | Inhoud | Rol in dit plan |
|---|---|---|
| `old_website` | 284 WebPlus-HTML-pagina's, 1.795 afbeeldingen. Consistente markup. | Canoniek archief; alleen nog referentie. |
| `ilsedelange-records-astro` (PR #1) | **De beste importbron.** `src/data/generated/pages.json` (283 getypeerde records: 60 releases, 161 songs, 24 appearances, 2 artiesten, 17 informatiepagina's, 33 utility), `media-manifest.json` (3.813 entries, 3.236 unieke blobs met SHA-256), `legacy-routes.json` (283 URL-mappings voor 301's), 305 release↔song↔info-relaties, facets (artiest/sectie/jaar/label), `spotify.json` (139 albums / 121 tracks gematcht). Deterministische importer met baseline-assertions en audits. | Primaire importbron voor WordPress. |
| `migration` | Oudere ETL (juni 2025); `public/content/lyrics.json` met 161 songs incl. **taaldetectie per song (en/nl)**. | Aanvullende bron: language-veld per lyric. |
| `ilsedelangerecords_web` (live) | React 19/Vite SPA, 32 albums + 161 lyrics uit JSON, GitHub-PR-contributiemodel, afbeeldingen ontbreken in repo. Half af. | Wordt vervangen; UI-patronen (filters/zoek) zijn bruikbare referentie. |

**Eisen uit de meeting van 02-09:** alles uit het oude archief behouden (incl. NL/DE-varianten van songs), filteren + zoeken, community-bijdragen met moderatie, SEO, ruimte voor AI-ondersteunde content.

## 2. Architectuur

Drie componenten in deze repo:

```
plugin/idr-discography/     datamodel + REST + import + contributie-moderatie
theme/idr-react/            WordPress-theme met React (Vite), server-side basis + React-islands
docs/                       dit plan, importverslagen, beslissingen
```

### 2.1 Plugin `idr-discography` (datamodel)

Custom post types en structuur, 1-op-1 afgeleid van de typed records in `pages.json`:

- **`idr_release`** — albums/singles/live/compilaties (60 stuks). Meta: format, jaar, label, catalogusnummer, edities (repeater), Spotify-URL, artiest. De 17 "information"-pagina's (tracklist & credits) worden meta/child van hun release, zoals de linkgraaf ze koppelt.
- **`idr_song`** — 161 songs. Meta: language (en/nl/de, uit migration-lyrics.json + contentdetectie), hasLyrics, `variant_of` (koppelt DE/NL-varianten van hetzelfde nummer aan elkaar), Spotify-track.
- **`idr_artist`** — Ilse DeLange, The Common Linnets (uitbreidbaar).
- **`idr_appearance`** — 24 gastbijdragen/collabs.
- Taxonomieën: `idr_section` (Album/Singles/Live/Other artist/Various artist/Items/TCL), jaar en label als queryable meta.
- Relaties (305 stuks) als post-meta-verwijzingen (release↔songs tracklistvolgorde, song↔appearance).
- REST-endpoints (`/wp-json/idr/v1/…`): releases, songs, artists met filter/zoek-parameters + één gecombineerd browse-index-endpoint dat de React-frontend in één call laadt.

### 2.2 Importpipeline (WP-CLI, in de plugin)

`wp idr import --source=<pad naar astro-repo>`:

1. Leest `pages.json`, `media-manifest.json`, `legacy-routes.json`, `spotify.json` + `lyrics.json` (migration-repo, language-veld).
2. Media: upload van de 3.236 unieke blobs naar de mediabibliotheek, dedup op SHA-256 (checksum in meta zodat herimport idempotent is).
3. Posts aanmaken per record-kind; relaties in een tweede pas (targetId → post-ID-mapping).
4. 301-redirects uit `legacy-routes.json` (oude WebPlus-URL's → nieuwe permalinks) via eigen rewrite-tabel in de plugin.
5. **Verificatie is onderdeel van de import** (zelfde filosofie als Geerts importer): assert 283 pagina's, 60 releases, 161 songs, 305 relaties, mediacounts; rapport naar `docs/import-report-<datum>.md`. Afwijkend = import faalt.

### 2.3 Theme `idr-react`

- Klassiek WP-theme als schil (SEO: server-gerenderde titels, meta, JSON-LD `MusicAlbum`/`MusicRecording`, sitemap) met **React-islands** (Vite-build) voor het interactieve deel: releases-browser met facetfilters (artiest/sectie/jaar/label), full-text zoeken over titels + lyrics, artiest-discografieën.
- Detailpagina's (release, song, artist) primair server-gerenderd zodat de site ook zonder JS leesbaar en indexeerbaar is; React hydrateert de interactieve blokken.
- Vormgeving: eigen identiteit, met de fotografische masthead-richting uit de Astro-PR als referentie. Geen stijl-transplantatie van elders.

### 2.4 Meertaligheid via STM

- STM levert de vertaal-infra (database-opslag, REST) voor **UI-strings en redactionele pagina's** in NL/EN/DE.
- **Lyrics zijn géén vertalingen** maar taalvarianten van een song (TCL heeft Duitse versies): die blijven aparte `idr_song`-posts gekoppeld via `variant_of`, met taalbadge en onderlinge links.
- Bekende STM-les toepassen: na elke NL-contentopschoning `clean-stale-translations` draaien (vertalingen hebben geen bron-hash).

### 2.5 Contributie-workflow (community)

- Frontend-formulier ("Vul aan / corrigeer") op elke release/song-pagina → **proposal-CPT in een moderatiewachtrij** (geen accounts nodig; naam/e-mail optioneel, honeypot + rate-limit tegen spam).
- Redactie (Geert e.a.) beoordeelt in wp-admin: accepteren = wijziging toepassen op de post, afwijzen = archiveren met reden. Elk voorstel logt diff + bron.
- Later uitbreidbaar met AI-assistentie (voorstel voorbewerken, bronnen checken) en met de eigen SEO-plugin voor contentgeneratie.

## 3. Fasering

| Fase | Inhoud | Klaar wanneer |
|---|---|---|
| **0. Fundering** | Repo-scaffold, lokale WP-dev-omgeving (docker of lokale stack), plugin-skelet, import-spike met 10 pagina's | Spike-import toont 10 correcte pagina's incl. media en 1 relatie |
| **1. Datamodel + import** | CPT's, relaties, volledige import + verificatierapport, 301-tabel | Assertions groen: 283/60/161/305; steekproef 20 pagina's pixel-vergelijkbaar qua inhoud |
| **2. React-theme** | Schil + islands: browse/filter/zoek, release/song/artist-templates, JSON-LD, sitemap | Feature-pariteit met huidige site + facetten; Lighthouse ≥ 90 |
| **3. Meertaligheid** | STM-integratie NL/EN/DE, variant-koppeling DE/NL-songs | UI omschakelbaar; alle DE/NL-varianten onderling gelinkt |
| **4. Community + go-live** | Contributie-formulier + moderatie, SEO-afronding, hosting + deploy, DNS-omzetting, 301's live | Eerste extern voorstel succesvol gemodereerd; oude URL's redirecten |

## 4. Hosting & deploy

WP-hosting in eigen beheer (zelfde patroon als de andere WordPress-sites: git-based deploy met de bestaande deploy-tooling). Domein `ilsedelangerecords.nl` wijst nu naar de huidige SPA; omzetting pas in fase 4 na een schaduwdraai op een testdomein.

## 5. Open punten (beslissen tijdens bouw)

- Naam/plek testdomein voor de schaduwdraai.
- Of de eigen SEO-plugin direct in fase 2 mee-installeert of pas bij go-live.
- Media: 489 MB origineel · WebP-derivaten genereren bij import of on-the-fly.
- Wie er naast Geert moderatierechten krijgt.
