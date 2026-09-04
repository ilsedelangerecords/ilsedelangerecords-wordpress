"""Deploy plugin + theme naar de WP-omgeving via FTPS en draai de activatieshim.

Creds komen uit de vault (project 6, cred 171). Gebruik:
    python tools/deploy.py            # code + shims + activatie
    python tools/deploy.py --media    # ook de 3.236 mediablobs (zip + server-side extract)
"""
import io
import json
import secrets
import subprocess
import sys
import zipfile
from pathlib import Path

import ftplib  # noqa: E402
import requests  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
ASTRO_MEDIA = Path(r"E:\projects\ilsedelange-records-astro\public\media\original")
# Rechtstreeks naar het server-IP met Host-header: DNS-onafhankelijk (lokale resolver
# haperde tijdens de eerste run) en werkt ook voordat DNS gepropageerd is.
BASE = "http://185.104.29.170"
UA = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0 Safari/537.36",
    "Host": "ilse.martiendejong.nl",
}


def get_credential(project_id, credential_id):
    """Vault-lookup, portabel over orchestratiehosts: op sommige hosts staat de
    jengo_vault-module op E:\\projects\\jengo, op deze host (geen E:-schijf)
    valt terug op de vault-api.py CLI die overal in jengo-system-private staat."""
    try:
        sys.path.insert(0, r"E:\projects\jengo")
        from jengo_vault import get_credential as _get  # noqa
        return _get(project_id, credential_id)
    except Exception:
        pass
    vault_api = Path(r"C:\projects\jengo\jengo-system-private\tools\vault-api.py")
    out = subprocess.run(
        [sys.executable, str(vault_api), "credential", str(project_id), str(credential_id)],
        capture_output=True, text=True, check=True,
    ).stdout
    return json.loads(out[out.index("{"):])


def http_get(url, **kwargs):
    import time
    last = None
    for attempt in range(5):
        try:
            return requests.get(url, **kwargs)
        except requests.exceptions.RequestException as e:
            last = e
            print(f"  http-retry {attempt + 1}: {type(e).__name__}")
            time.sleep(5 * (attempt + 1))
    raise last


class ReusedSslFTP(ftplib.FTP_TLS):
    """zxcs vereist TLS-session-reuse op de dataverbinding (anders 425)."""

    def ntransfercmd(self, cmd, rest=None):
        conn, size = ftplib.FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            conn = self.context.wrap_socket(conn, server_hostname=self.host, session=self.sock.session)
        return conn, size


def connect():
    cred = get_credential(6, 171)
    ftp = ReusedSslFTP("web0168.zxcs.nl", timeout=120)
    ftp.login(cred["username"], cred["password"])
    ftp.prot_p()
    return ftp


def ensure_dir(ftp, path):
    parts = path.strip("/").split("/")
    for i in range(1, len(parts) + 1):
        try:
            ftp.mkd("/" + "/".join(parts[:i]))
        except ftplib.error_perm:
            pass


def upload_tree(ftp, local, remote):
    ensure_dir(ftp, remote)
    for item in sorted(Path(local).rglob("*")):
        rel = item.relative_to(local).as_posix()
        target = f"{remote}/{rel}"
        if item.is_dir():
            ensure_dir(ftp, target)
        else:
            with item.open("rb") as fh:
                ftp.storbinary(f"STOR /{target.strip('/')}", fh)
            print("  up:", target)


def upload_bytes(ftp, remote, data: bytes):
    ftp.storbinary(f"STOR /{remote.strip('/')}", io.BytesIO(data))


def patch_wp_config(ftp, token):
    buf = io.BytesIO()
    ftp.retrbinary("RETR /public_html/wp-config.php", buf.write)
    config = buf.getvalue().decode("utf-8")
    config = "\n".join(l for l in config.splitlines() if "IDR_IMPORT_TOKEN" not in l)
    config = config.replace("$table_prefix", f"define( 'IDR_IMPORT_TOKEN', '{token}' );\n$table_prefix", 1)
    upload_bytes(ftp, "public_html/wp-config.php", config.encode("utf-8"))
    print("  wp-config: IDR_IMPORT_TOKEN gezet")


# EN/DE-vertalingen voor de __stm()/_e_stm()-sleutels in het theme (context: 'general',
# STM's default). NL is de brontaal en wordt automatisch geseed door
# StringScanner::scan_and_register() (de fallback-tekst in de __stm()-call zelf) —
# hier alleen de twee vertaalde talen. Zie idr-i18n.php voor de bronsleutels.
UI_STRINGS_EN_DE = {
    "nav.home": ("Home", "Startseite"),
    "nav.releases": ("Releases", "Veröffentlichungen"),
    "nav.songs": ("Lyrics & songs", "Songtexte"),
    "nav.appearances": ("Guest appearances", "Gastbeiträge"),
    "search.placeholder": ("Search the archive", "Archiv durchsuchen"),
    "footer.closer": ("Collected by fans, pressing by pressing.", "Gesammelt von Fans, Pressung für Pressung."),
    "footer.disclaimer": ("fan archive, not an official site", "Fan-Archiv, keine offizielle Website"),
    "hero.eyebrow": ("The record archive · since the first pressing", "Das Plattenarchiv · seit der ersten Pressung"),
    "hero.title": (
        "Every release, every pressing, every lyric. <em>Documented.</em>",
        "Jede Veröffentlichung, jede Pressung, jeder Songtext. <em>Dokumentiert.</em>",
    ),
    "hero.intro": (
        "The complete archive of Ilse DeLange and The Common Linnets: albums, singles, live "
        "recordings, promo items and lyrics, with the sleeves and pressings from the collection.",
        "Das Sammelarchiv von Ilse DeLange und The Common Linnets: Alben, Singles, Live-Aufnahmen, "
        "Promo-Artikel und Songtexte, mit den Covern und Pressungen aus der Sammlung.",
    ),
    "hero.browse": ("Browse the collection", "Sammlung durchstöbern"),
    "hero.scans": ("scans in the collection", "Scans in der Sammlung"),
    "hero.sound_on": ("Sound on", "Ton an"),
    "home.recent": ("Recently added", "Neu in der Sammlung"),
    "home.all_releases": ("All %d releases \u2192", "Alle %d Veröffentlichungen \u2192"),
    "home.lyrics_heading": ("Lyrics", "Songtexte"),
    "home.all_songs": ("All songs \u2192", "Alle Songs \u2192"),
    "song.listen_spotify": ("Listen on Spotify", "Auf Spotify hören"),
    "song.variants_heading": ("Also in the archive", "Auch im Archiv"),
    "song.related_heading": ("Related in the archive", "Verwandtes im Archiv"),
    "contribute.heading": ("Add or correct something", "Etwas ergänzen oder korrigieren"),
    "contribute.intro": (
        "Spotted an error or something missing? The editors review every suggestion by hand, no account needed.",
        "Einen Fehler entdeckt oder fehlt etwas? Die Redaktion prüft jeden Vorschlag von Hand, kein Konto nötig.",
    ),
    "contribute.sent": ("Thanks! Your suggestion is in the moderation queue.", "Danke! Dein Vorschlag steht in der Moderationswarteschlange."),
    "contribute.ratelimited": ("Too many suggestions sent recently, please try again later.", "Zu viele Vorschläge kürzlich gesendet, bitte versuche es später erneut."),
    "contribute.error": ("Something went wrong, please add an explanation and try again.", "Etwas ist schiefgelaufen, bitte gib eine Erklärung ein und versuche es erneut."),
    "contribute.field_label": ("What's wrong or missing?", "Was stimmt nicht oder fehlt?"),
    "contribute.value_label": ("Suggested value (if applicable)", "Vorgeschlagener Wert (falls zutreffend)"),
    "contribute.message_label": ("Explanation", "Erklärung"),
    "contribute.source_label": ("Source (link, optional)", "Quelle (Link, optional)"),
    "contribute.name_label": ("Name (optional)", "Name (optional)"),
    "contribute.email_label": ("Email (optional)", "E-Mail (optional)"),
    "contribute.submit": ("Send suggestion", "Vorschlag senden"),
}


ACTIVATE_SHIM = """<?php
if (($_GET['token'] ?? '') !== '%TOKEN%') { http_response_code(403); exit('no'); }
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
$out = [];
// Volgorde is van belang: idr-discography registreert het stm_default_languages-filter
// bij het laden (top-level add_filter), dus die moet actief zijn voordat STM's eigen
// activatiehaak (Database::seed_default_languages()) draait. De theme-switch moet vóór
// STM's activatie plaatsvinden zodat StringScanner::scan_and_register() het idr-react
// theme scant i.p.v. het vorige default-theme.
$out['plugin'] = activate_plugin('idr-discography/idr-discography.php');
switch_theme('idr-react');
$out['theme'] = wp_get_theme()->get('Name');
$out['stm_plugin'] = activate_plugin('simple-translation-manager/simple-translation-manager.php');
if (class_exists('STM\\\\StringScanner')) {
    $out['string_scan'] = STM\\StringScanner::scan_and_register();
}
update_option('permalink_structure', '/%postname%/');
update_option('blogdescription', 'Het discografie-archief van Ilse DeLange & The Common Linnets');
update_option('timezone_string', 'Europe/Amsterdam');
foreach ([1, 2, 3] as $sample) { wp_delete_post($sample, true); }
global $wp_rewrite;
$wp_rewrite->set_permalink_structure('/%postname%/');
flush_rewrite_rules(true);
$out['permalinks'] = get_option('permalink_structure');
$out['htaccess'] = file_exists(__DIR__ . '/.htaccess');
$out['languages'] = class_exists('STM\\\\Database') ? wp_list_pluck(STM\\Database::get_languages(), 'code') : [];
header('Content-Type: application/json');
echo json_encode($out);
"""

EXTRACT_SHIM = """<?php
if (($_GET['token'] ?? '') !== '%TOKEN%') { http_response_code(403); exit('no'); }
set_time_limit(600);
$zip = __DIR__ . '/media/' . basename($_GET['zip'] ?? '');
if (!is_file($zip)) { exit('zip missing: ' . $zip); }
$za = new ZipArchive();
if ($za->open($zip) !== true) { exit('open failed'); }
$za->extractTo(__DIR__ . '/media/original/');
$n = $za->numFiles;
$za->close();
unlink($zip);
echo "OK $n files";
"""

# Seed de EN/DE UI-string-vertalingen. STRINGS_JSON is een JSON-object
# {sleutel: [en, de]}; wp_stm_strings-rijen bestaan al na de scan hierboven
# (met de NL-fallback als brontaal), dit voegt alleen de twee vertaalde talen toe.
SEED_STRINGS_SHIM = """<?php
if (($_GET['token'] ?? '') !== '%TOKEN%') { http_response_code(403); exit('no'); }
require __DIR__ . '/wp-load.php';
global $wpdb;
$strings = json_decode('%STRINGS_JSON%', true);
$t_strings = $wpdb->prefix . 'stm_strings';
$t_translations = $wpdb->prefix . 'stm_translations';
$upserted = 0; $missing = [];
foreach ($strings as $key => $langs) {
    $string_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$t_strings} WHERE string_key = %s AND context = 'general'", $key
    ));
    if (!$string_id) { $missing[] = $key; continue; }
    foreach (['en' => $langs[0], 'de' => $langs[1]] as $lang => $text) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t_translations} WHERE string_id = %d AND language_code = %s", $string_id, $lang
        ));
        $data = ['string_id' => $string_id, 'language_code' => $lang, 'translation' => $text, 'status' => 'published'];
        if ($existing) { $wpdb->update($t_translations, $data, ['id' => $existing]); }
        else { $wpdb->insert($t_translations, $data); }
        $upserted++;
    }
}
if (function_exists('wp_cache_flush')) { wp_cache_flush(); }
header('Content-Type: application/json');
echo json_encode(['upserted' => $upserted, 'missing_keys' => $missing]);
"""


def do_media(ftp, token):
    files = sorted(ASTRO_MEDIA.iterdir())
    print(f"media: {len(files)} blobs zippen in chunks...")
    ensure_dir(ftp, "public_html/media/original")
    chunk, size, idx = [], 0, 0
    LIMIT = 120 * 1024 * 1024

    def flush(chunk, idx):
        buf = io.BytesIO()
        with zipfile.ZipFile(buf, "w", zipfile.ZIP_STORED) as zf:
            for f in chunk:
                zf.write(f, f.name)
        data = buf.getvalue()
        name = f"blobs-{idx}.zip"
        print(f"  chunk {idx}: {len(chunk)} files, {len(data) // 1048576} MB uploaden...")
        upload_bytes(ftp, f"public_html/media/{name}", data)
        r = http_get(f"{BASE}/idr-extract.php", params={"token": token, "zip": name},
                     timeout=600, headers=UA)
        print("  extract:", r.status_code, r.text[:100])
        if not r.text.startswith("OK"):
            sys.exit("EXTRACT MISLUKT")

    for f in files:
        chunk.append(f)
        size += f.stat().st_size
        if size >= LIMIT:
            flush(chunk, idx)
            chunk, size, idx = [], 0, idx + 1
    if chunk:
        flush(chunk, idx)


def main():
    token = secrets.token_hex(16)
    ftp = connect()

    print("code uploaden...")
    upload_tree(ftp, ROOT / "plugin" / "idr-discography", "public_html/wp-content/plugins/idr-discography")
    upload_tree(ftp, ROOT / "plugin" / "simple-translation-manager", "public_html/wp-content/plugins/simple-translation-manager")
    upload_tree(ftp, ROOT / "theme" / "idr-react", "public_html/wp-content/themes/idr-react")
    patch_wp_config(ftp, token)
    upload_bytes(ftp, "public_html/idr-activate.php", ACTIVATE_SHIM.replace("%TOKEN%", token).encode())
    upload_bytes(ftp, "public_html/idr-extract.php", EXTRACT_SHIM.replace("%TOKEN%", token).encode())
    strings_json = json.dumps(
        {k: list(v) for k, v in UI_STRINGS_EN_DE.items()}, ensure_ascii=False
    ).replace("\\", "\\\\").replace("'", "\\'")
    upload_bytes(
        ftp, "public_html/idr-seed-strings.php",
        SEED_STRINGS_SHIM.replace("%TOKEN%", token).replace("%STRINGS_JSON%", strings_json).encode("utf-8"),
    )

    if "--media" in sys.argv:
        do_media(ftp, token)

    print("activeren...")
    r = http_get(f"{BASE}/idr-activate.php", params={"token": token}, timeout=120, headers=UA)
    print("  ", r.status_code, r.text[:500])

    print("EN/DE UI-strings seeden...")
    r = http_get(f"{BASE}/idr-seed-strings.php", params={"token": token}, timeout=60, headers=UA)
    print("  ", r.status_code, r.text[:500])

    for shim in ("idr-activate.php", "idr-extract.php", "idr-seed-strings.php"):
        try:
            ftp.delete(f"/public_html/{shim}")
        except ftplib.error_perm:
            pass
    ftp.quit()
    (ROOT / ".import-token").write_text(token, encoding="utf-8")
    print("klaar · import-token in .import-token")


if __name__ == "__main__":
    main()
