"""Bouwt import-payload.json uit de astro-typed-data (ilsedelange-records-astro).

Port van src/data/facets.ts + catalog.ts uit die repo: secties, artiest-toewijzing,
jaar/label/catalogusnummer-extractie, formaat-fallback, chrome-media-filter,
coverkeuze, legacy-HTML-opschoning en de release<->information-koppeling.
"""
import json
import re
import sys
from pathlib import Path

ASTRO = Path(r"E:\projects\ilsedelange-records-astro")
MIGRATION_LYRICS = Path(r"E:\projects\idr-migration\public\content\lyrics.json")
OUT = Path(__file__).resolve().parent.parent / "import-payload.json"

NAVIGATION_SPEC = [
    ("Home", "index.html"), ("Album", "albums.html"), ("Singles", "singles.html"),
    ("Live", "live.html"), ("Other artist", "other artist.html"),
    ("Various artist", "Various artist.html"), ("Items", "items.html"),
    ("Lyrics", "lyrics.html"), ("TCL album", "tcl album.html"),
    ("TCL singles", "TCL singles.html"), ("TCL other", "TCL other.html"),
    ("TCL various", "TCL various.html"), ("TCL lyrics", "TCL lyrics.html"),
]
TCL_SECTIONS = {"TCL album", "TCL singles", "TCL other", "TCL various", "TCL lyrics"}
FORMAT_BY_SECTION = {"Album": "album", "Singles": "single", "Live": "live",
                     "TCL album": "album", "TCL singles": "single"}
CHROME_MEDIA = re.compile(r"^(?:20 years banner|TCL banner|Facebook(?:-1)?|dawn(?:-\d+)?)\.(?:jpe?g|png)$", re.I)


def load(name):
    return json.loads((ASTRO / "src/data/generated" / name).read_text(encoding="utf-8"))


def read_field(text, field):
    pattern = re.compile(
        field + r"\s*:?\s*(.{0,60}?)(?=\s{2,}|\s*(?:Record label|Released|Catalog number|Extra)\b|[\n\r]|$)",
        re.I)
    m = pattern.search(text)
    if not m:
        return None
    value = m.group(1).strip().rstrip(",;")
    return value or None


def read_year(value):
    if not value:
        return None
    m = re.search(r"\b(19[5-9]\d|20[0-5]\d)\b", value)
    return int(m.group(1)) if m else None


def display_title(page):
    t = page["source"]["canonicalTitle"]
    t = re.sub(r"^\s*(?:www\.)?ilsedelangerecords\.nl\s*[-–]\s*", "", t, flags=re.I)
    t = re.sub(r"^\s*-\s*", "", t).strip()
    return t or page["source"]["canonicalTitle"]


def content_media(page):
    return [m for m in page["media"] if not CHROME_MEDIA.match(m["sourcePath"])]


def cover_image(page):
    media = content_media(page)
    for m in media:
        if re.search(r"\b(?:front|cover|voor)\b", f"{m.get('alt', '')} {m['sourcePath']}", re.I):
            return m
    return media[0] if media else None


def clean_html(page, chrome_urls):
    html = page["html"]
    html = re.sub(r"<map\b[^>]*>[\s\S]*?</map>", "", html, flags=re.I)

    def img_filter(match):
        return "" if match.group(1) in chrome_urls else match.group(0)

    html = re.sub(r'<img\b[^>]*\bsrc="([^"]+)"[^>]*>', img_filter, html, flags=re.I)

    def iframe_fix(match):
        src = match.group(1)
        video = re.search(r"(?:youtube(?:-nocookie)?\.com/embed/|youtu\.be/)([\w-]{6,})", src, re.I)
        if not video:
            return match.group(0)
        return ('<iframe class="legacy-embed" src="https://www.youtube-nocookie.com/embed/'
                + video.group(1) + '?rel=0" title="Archive video" loading="lazy" allowfullscreen></iframe>')

    return re.sub(r'<iframe\b[^>]*\bsrc="([^"]+)"[^>]*></iframe>', iframe_fix, html, flags=re.I)


def main():
    pages = load("pages.json")
    routes = load("legacy-routes.json")
    manifest = load("media-manifest.json")
    spotify = load("spotify.json").get("albums", {})
    by_id = {p["id"]: p for p in pages}

    chrome_urls = {m["publicPath"] for m in manifest if CHROME_MEDIA.match(m["sourcePath"])}

    # taal per song uit de migration-repo (titelmatch, lowercased)
    language_by_title = {}
    if MIGRATION_LYRICS.exists():
        for lyr in json.loads(MIGRATION_LYRICS.read_text(encoding="utf-8")).get("lyrics", []):
            if lyr.get("language"):
                language_by_title[lyr["title"].strip().lower()] = lyr["language"]

    # secties via de navigatie-hubs (facets.ts)
    by_filename = {p["source"]["legacyFilename"]: p for p in pages}
    nav = []
    for label, filename in NAVIGATION_SPEC:
        page = by_filename.get(filename)
        if page:
            nav.append({"label": label, "pageId": page["id"]})
    section_by_id = {}
    for item in nav:
        if item["label"] == "Home":
            continue
        hub = by_id[item["pageId"]]
        for link in hub["links"]:
            if not link.get("internal") or not link.get("targetId"):
                continue
            if link["targetId"] == hub["id"] or link["targetId"] in section_by_id:
                continue
            section_by_id[link["targetId"]] = item["label"]
    for item in nav:
        if item["label"] != "Home":
            section_by_id[item["pageId"]] = item["label"]

    # release <-> information via het "Information"-label (catalog.ts)
    info_page_of, record_of_info = {}, {}
    for page in pages:
        for link in page["links"]:
            if link.get("internal") and link.get("targetId") and re.search(r"information", link["label"], re.I):
                info_page_of[page["id"]] = link["targetId"]
                record_of_info[link["targetId"]] = page["id"]

    def uses_tcl(page):
        return any(re.match(r"^TCL banner\.jpg$", m["sourcePath"], re.I) for m in page["media"])

    def information_parent(page):
        if page["kind"] != "utility" or page.get("utilityType") != "information":
            return None
        subject = re.sub(r",?\s*Information$", "", page["source"]["canonicalTitle"], flags=re.I).strip().lower()
        if not subject:
            return None
        for cand in pages:
            if cand["kind"] == "release" and subject in cand["source"]["canonicalTitle"].lower():
                return cand
        return None

    def facets(page):
        parent = information_parent(page)
        section = section_by_id.get(page["id"]) or (parent and section_by_id.get(parent["id"]))
        artist = ("the-common-linnets" if (section in TCL_SECTIONS) or uses_tcl(page) else "ilse-delange")
        text = page["plainText"] + " " + (parent["plainText"] if parent else "")
        released = read_field(text, "Released")
        fmt = None
        if page["kind"] == "release":
            fmt = page["release"]["format"]
            if fmt == "unknown":
                fmt = FORMAT_BY_SECTION.get(section, "unknown")
        return {
            "section": section, "artist": artist,
            "year": read_year(released) or read_year(text),
            "released_text": released,
            "label": read_field(text, "Record label"),
            "catalog_number": read_field(text, "Catalog number"),
            "format": fmt,
        }

    nav_page_ids = {item["pageId"] for item in nav}
    # artist-pagina's (ilse-delange, tcl-info) blijven zelfstandige posts, ook al wijst
    # een release met een "Information"-knop naar ze (importer typeert ze als artist)
    info_ids = {i for i in record_of_info if by_id[i]["kind"] != "artist"}
    items, skipped = [], []

    for page in pages:
        pid = page["id"]
        if pid in nav_page_ids:
            skipped.append((pid, "nav-hub"))
            continue
        if pid in info_ids:
            skipped.append((pid, "embedded-in-release"))
            continue
        kind_map = {"release": "release", "song": "song", "artist": "artist",
                    "appearance": "appearance", "utility": "page", "media": "page", "editorial": "page"}
        kind = kind_map[page["kind"]]
        f = facets(page)
        cover = cover_image(page)
        related = [
            {"label": link["label"], "targetId": record_of_info.get(link["targetId"], link["targetId"])}
            for link in page["links"]
            if link.get("internal") and link.get("targetId")
            and link["targetId"] not in nav_page_ids and link["targetId"] != pid
        ]
        sp = spotify.get(pid) or {}
        meta = {
            "year": f["year"], "released_text": f["released_text"], "label": f["label"],
            "catalog_number": f["catalog_number"], "format": f["format"],
            "spotify_url": sp.get("url"), "spotify_name": sp.get("name"),
            "cover": cover["url"] if cover else None,
            "media": content_media(page) or None,
            "related": related or None,
            "display_title": display_title(page),
            "legacy_filename": page["source"]["legacyFilename"],
            "source_description": page["source"].get("sourceDescription"),
        }
        if page["kind"] == "song":
            meta["has_lyrics"] = 1 if page["song"]["hasLyrics"] else None
            meta["language"] = language_by_title.get(display_title(page).strip().lower())
        if page["kind"] == "release":
            info_id = info_page_of.get(pid)
            if info_id and info_id in by_id:
                meta["info_html"] = clean_html(by_id[info_id], chrome_urls)
        items.append({
            "id": pid, "kind": kind, "slug": page["slug"],
            "title": display_title(page),
            "content": clean_html(page, chrome_urls),
            "excerpt": page["source"].get("sourceDescription") or "",
            "section": f["section"], "artist": f["artist"],
            "format": f["format"] if page["kind"] == "release" else None,
            "meta": {k: v for k, v in meta.items() if v is not None},
        })

    # legacy-routes: info-pagina's en nav-hubs verwijzen door naar hun vervangende record
    route_payload = []
    for route in routes:
        target = route["targetId"]
        if target in record_of_info:
            target = record_of_info[target]
        route_payload.append({"filename": route["filename"], "targetId": target})

    payload = {
        "items": items,
        "legacy_routes": route_payload,
        "sections": [item["label"] for item in nav if item["label"] != "Home"],
        "report": {
            "source_pages": len(pages), "imported_items": len(items),
            "skipped": skipped, "routes": len(route_payload),
            "kinds": {k: sum(1 for i in items if i["kind"] == k) for k in {i["kind"] for i in items}},
        },
    }
    OUT.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    print("payload:", OUT)
    print("items:", len(items), "| routes:", len(route_payload), "| skipped:", len(skipped))
    print("kinds:", payload["report"]["kinds"])
    langs = [i for i in items if i["kind"] == "song" and i["meta"].get("language")]
    print("songs met taal:", len(langs), "| met lyrics:",
          sum(1 for i in items if i["meta"].get("has_lyrics")))
    covers = sum(1 for i in items if i["meta"].get("cover"))
    print("met cover:", covers, "| met spotify:", sum(1 for i in items if i["meta"].get("spotify_url")))


if __name__ == "__main__":
    main()
