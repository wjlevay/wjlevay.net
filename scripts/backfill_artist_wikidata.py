#!/usr/bin/env python3
from __future__ import annotations

import csv
import html
import io
import re
import subprocess
from pathlib import Path

import requests


REPORT_HEADERS = [
    "term_id",
    "name",
    "count",
    "status",
    "wikidata_id",
    "label",
    "description",
    "matched_on",
]

ALLOWED_DESCRIPTION_KEYWORDS = {
    "band",
    "musician",
    "singer",
    "rapper",
    "songwriter",
    "composer",
    "orchestra",
    "ensemble",
    "duo",
    "trio",
    "quartet",
    "group",
    "artist",
    "dj",
    "comedian",
    "actor",
    "actress",
    "theatrical",
    "musical",
    "play",
    "ballet",
    "dance",
    "dance company",
    "performance group",
    "baseball team",
    "basketball team",
    "football team",
    "hockey team",
    "sports team",
    "festival",
    "concert series",
    "show",
}

SKIP_EXACT = {
    "all",
    "indiana",
    "syracuse",
}


def run_wp(args: list[str], check: bool = True) -> str:
    result = subprocess.run(
        ["ddev", "wp", *args],
        cwd="/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child",
        capture_output=True,
        text=True,
        check=check,
    )
    return result.stdout.strip()


def wp_csv(args: list[str]) -> list[dict[str, str]]:
    return list(csv.DictReader(io.StringIO(run_wp(args))))


def normalize(text: str) -> str:
    value = html.unescape(text).strip().lower()
    value = value.replace("&amp;", "&")
    value = re.sub(r"\(.*?\)", "", value)
    value = re.sub(r"[^a-z0-9&+]+", " ", value)
    return re.sub(r"\s+", " ", value).strip()


def has_allowed_description(description: str) -> bool:
    desc = description.lower()
    return any(keyword in desc for keyword in ALLOWED_DESCRIPTION_KEYWORDS)


def search_wikidata(name: str) -> list[dict]:
    response = requests.get(
        "https://www.wikidata.org/w/api.php",
        params={
            "action": "wbsearchentities",
            "search": name,
            "language": "en",
            "format": "json",
            "limit": 10,
            "type": "item",
        },
        headers={
            "Accept": "application/json",
            "User-Agent": "wjlevay-net/1.0 (metadata cleanup script; https://wjlevay.net)",
        },
        timeout=30,
    )
    response.raise_for_status()
    return response.json().get("search", [])


def choose_candidate(term_name: str, results: list[dict]) -> tuple[dict | None, str]:
    needle = normalize(term_name)
    if not needle or needle in SKIP_EXACT:
        return None, "skipped-generic"

    exact_label: dict | None = None
    exact_alias: dict | None = None
    fallback: dict | None = None

    for result in results:
        label = str(result.get("label", ""))
        description = str(result.get("description", ""))
        aliases = result.get("aliases") or []

        if not has_allowed_description(description):
            continue

        norm_label = normalize(label)
        norm_aliases = {normalize(alias) for alias in aliases if isinstance(alias, str)}

        if norm_label == needle:
            exact_label = result
            break

        if needle in norm_aliases and not exact_alias:
            exact_alias = result

        if not fallback and (needle in norm_label or norm_label in needle):
            fallback = result

    if exact_label:
        return exact_label, "exact-label"
    if exact_alias:
        return exact_alias, "exact-alias"
    if fallback:
        return fallback, "partial-label"
    return None, "no-confident-match"


def main() -> int:
    report_path = Path("staging/reports/artist-wikidata-backfill.csv")
    report_path.parent.mkdir(parents=True, exist_ok=True)

    terms = wp_csv(["term", "list", "artist", "--fields=term_id,name,slug,count", "--format=csv"])
    rows: list[dict[str, str]] = []
    updated = 0
    skipped_existing = 0

    for term in terms:
        term_id = term["term_id"]
        existing = run_wp(["term", "meta", "get", term_id, "wikidata_id"], check=False).strip()
        if existing:
            skipped_existing += 1
            rows.append(
                {
                    "term_id": term_id,
                    "name": term["name"],
                    "count": term["count"],
                    "status": "already-set",
                    "wikidata_id": existing,
                    "label": "",
                    "description": "",
                    "matched_on": "",
                }
            )
            continue

        results = search_wikidata(term["name"])
        candidate, matched_on = choose_candidate(term["name"], results)

        if candidate:
            wikidata_id = str(candidate.get("id", ""))
            run_wp(["term", "meta", "update", term_id, "wikidata_id", wikidata_id])
            updated += 1
            rows.append(
                {
                    "term_id": term_id,
                    "name": term["name"],
                    "count": term["count"],
                    "status": "updated",
                    "wikidata_id": wikidata_id,
                    "label": str(candidate.get("label", "")),
                    "description": str(candidate.get("description", "")),
                    "matched_on": matched_on,
                }
            )
            continue

        rows.append(
            {
                "term_id": term_id,
                "name": term["name"],
                "count": term["count"],
                "status": matched_on,
                "wikidata_id": "",
                "label": "",
                "description": "",
                "matched_on": matched_on,
            }
        )

    with report_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=REPORT_HEADERS)
        writer.writeheader()
        writer.writerows(rows)

    print(report_path)
    print(f"updated={updated}")
    print(f"already_set={skipped_existing}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
