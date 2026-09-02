"""Voert de contentimport uit tegen het idr/v1-import-endpoint en verifieert de counts."""
import json
import sys
from pathlib import Path

import requests

ROOT = Path(__file__).resolve().parent.parent
BASE = "http://ilse.martiendejong.nl"
UA = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0 Safari/537.36"}
BATCH = 20

EXPECTED = {"release": 60, "song": 161, "artist": 2, "appearance": 24, "page": 6, "legacy_routes": 283}


def main():
    token = (ROOT / ".import-token").read_text(encoding="utf-8").strip()
    payload = json.loads((ROOT / "import-payload.json").read_text(encoding="utf-8"))
    headers = {**UA, "X-IDR-Token": token}

    items = payload["items"]
    total = 0
    for i in range(0, len(items), BATCH):
        batch = items[i:i + BATCH]
        r = requests.post(f"{BASE}/wp-json/idr/v1/import", json={"items": batch},
                          headers=headers, timeout=300)
        if r.status_code != 200:
            sys.exit(f"batch {i}: HTTP {r.status_code}: {r.text[:300]}")
        body = r.json()
        errors = [x for x in body["results"] if "error" in x]
        total += body["imported"]
        print(f"batch {i // BATCH + 1}: +{body['imported']} (totaal {total})"
              + (f" ERRORS: {errors}" if errors else ""))
        if errors:
            sys.exit("import-fouten, gestopt")

    r = requests.post(f"{BASE}/wp-json/idr/v1/import-meta", headers=headers, timeout=120, json={
        "legacy_routes": payload["legacy_routes"],
        "sections": payload["sections"],
        "report": payload["report"],
    })
    print("meta:", r.status_code, r.text[:120])

    status = requests.get(f"{BASE}/wp-json/idr/v1/status", headers=UA, timeout=60).json()
    print("status:", status)
    failures = {k: (status.get(k), v) for k, v in EXPECTED.items() if status.get(k) != v}
    if failures:
        sys.exit(f"VERIFICATIE MISLUKT: {failures}")
    print("VERIFICATIE OK: alle counts kloppen met de bron.")


if __name__ == "__main__":
    main()
