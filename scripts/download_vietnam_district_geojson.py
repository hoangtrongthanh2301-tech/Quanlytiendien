#!/usr/bin/env python3
import json
import os
import sys
import urllib.request
from urllib.error import HTTPError, URLError

CANDIDATE_URLS = [
    'https://raw.githubusercontent.com/fititnt/vietnam-geojson/master/districts.geojson',
    'https://raw.githubusercontent.com/fititnt/vietnam-geojson/master/geojson/districts.geojson',
    'https://raw.githubusercontent.com/fititnt/vietnam-geojson/master/vietnam-districts.geojson',
    'https://raw.githubusercontent.com/fititnt/vietnam-geojson/master/vn_districts.geojson',
    'https://raw.githubusercontent.com/tuandm/geojson-vietnam/master/districts.geojson',
]

OUTPUT_PATH = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'assets', 'data', 'vn_districts.geojson'))


def try_download(url):
    try:
        with urllib.request.urlopen(url, timeout=30) as response:
            payload = response.read()
            if not payload:
                return None
            try:
                json.loads(payload.decode('utf-8'))
                return payload
            except Exception:
                return None
    except (HTTPError, URLError, TimeoutError, ValueError):
        return None


def main():
    os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
    for url in CANDIDATE_URLS:
        payload = try_download(url)
        if payload is None:
            continue
        with open(OUTPUT_PATH, 'w', encoding='utf-8') as fp:
            fp.write(payload.decode('utf-8'))
        print(f'Downloaded district GeoJSON from: {url}')
        print(f'Saved to: {OUTPUT_PATH}')
        return 0

    print('No working district GeoJSON source was found for Vietnam.')
    print('Tried URLs:')
    for url in CANDIDATE_URLS:
        print(f' - {url}')
    return 1


if __name__ == '__main__':
    raise SystemExit(main())
