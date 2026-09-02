"""Deploy plugin + theme naar de WP-omgeving via FTPS en draai de activatieshim.

Creds komen uit de vault (project 6, cred 171). Gebruik:
    python tools/deploy.py            # code + shims + activatie
    python tools/deploy.py --media    # ook de 3.236 mediablobs (zip + server-side extract)
"""
import io
import json
import secrets
import sys
import zipfile
from pathlib import Path

sys.path.insert(0, r"E:\projects\jengo")
from jengo_vault import get_credential  # noqa: E402
import ftplib  # noqa: E402
import requests  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
ASTRO_MEDIA = Path(r"E:\projects\ilsedelange-records-astro\public\media\original")
BASE = "http://ilse.martiendejong.nl"
UA = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0 Safari/537.36"}


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


ACTIVATE_SHIM = """<?php
if (($_GET['token'] ?? '') !== '%TOKEN%') { http_response_code(403); exit('no'); }
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
$out = [];
$out['plugin'] = activate_plugin('idr-discography/idr-discography.php');
switch_theme('idr-react');
$out['theme'] = wp_get_theme()->get('Name');
update_option('permalink_structure', '/%postname%/');
update_option('blogdescription', 'Het discografie-archief van Ilse DeLange & The Common Linnets');
update_option('timezone_string', 'Europe/Amsterdam');
foreach ([1, 2, 3] as $sample) { wp_delete_post($sample, true); }
global $wp_rewrite;
$wp_rewrite->set_permalink_structure('/%postname%/');
flush_rewrite_rules(true);
$out['permalinks'] = get_option('permalink_structure');
$out['htaccess'] = file_exists(__DIR__ . '/.htaccess');
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
        r = requests.get(f"{BASE}/idr-extract.php", params={"token": token, "zip": name},
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
    upload_tree(ftp, ROOT / "theme" / "idr-react", "public_html/wp-content/themes/idr-react")
    patch_wp_config(ftp, token)
    upload_bytes(ftp, "public_html/idr-activate.php", ACTIVATE_SHIM.replace("%TOKEN%", token).encode())
    upload_bytes(ftp, "public_html/idr-extract.php", EXTRACT_SHIM.replace("%TOKEN%", token).encode())

    if "--media" in sys.argv:
        do_media(ftp, token)

    print("activeren...")
    r = requests.get(f"{BASE}/idr-activate.php", params={"token": token}, timeout=120, headers=UA)
    print("  ", r.status_code, r.text[:300])

    for shim in ("idr-activate.php", "idr-extract.php"):
        try:
            ftp.delete(f"/public_html/{shim}")
        except ftplib.error_perm:
            pass
    ftp.quit()
    (ROOT / ".import-token").write_text(token, encoding="utf-8")
    print("klaar · import-token in .import-token")


if __name__ == "__main__":
    main()
