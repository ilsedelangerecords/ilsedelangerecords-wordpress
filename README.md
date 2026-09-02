# ilsedelangerecords-wordpress

WordPress-implementatie van [ilsedelangerecords.nl](https://ilsedelangerecords.nl): custom plugin (`idr-discography`) met het discografie-datamodel en importpipeline, plus een React-theme (`idr-react`) met facet-browsing en zoeken. Vertalingen (NL/EN/DE) via [Simple Translation Manager](https://github.com/martiendejong/simple-translation-manager).

Zie [PLAN.md](PLAN.md) voor architectuur, fasering en de analyse van de bestaande repos (WebPlus-archief, Astro-migratie, huidige SPA).

## Structuur

```
plugin/idr-discography/   datamodel (CPT's), REST API, WP-CLI-import, contributie-moderatie
theme/idr-react/          WP-theme: server-gerenderde schil + React-islands (Vite)
docs/                     plan, importverslagen, beslissingen
```

## Databronnen voor de import

- [ilsedelange-records-astro](https://github.com/ilsedelangerecords/ilsedelange-records-astro) PR #1 · `src/data/generated/` (pages.json, media-manifest.json, legacy-routes.json, spotify.json)
- [migration](https://github.com/ilsedelangerecords/migration) · `public/content/lyrics.json` (language-veld per song)
