#!/usr/bin/env python3
from __future__ import annotations

import csv
import io
import subprocess
from pathlib import Path


CURATED = {
    "Bjork": {
        "wikidata_id": "Q42455",
        "label": "Björk",
        "description": "Icelandic singer (born 1965)",
        "matched_on": "manual-curated",
        "rename_to": "Björk",
    },
    "Lee Scratch Perry": {
        "wikidata_id": "Q315417",
        "label": 'Lee "Scratch" Perry',
        "description": "Jamaican reggae producer (1936–2021)",
        "matched_on": "manual-curated",
    },
    "Sharon Jones &amp; The Dap-Kings": {
        "wikidata_id": "Q3288013",
        "label": "Sharon Jones & The Dap-Kings",
        "description": "American funk/soul band",
        "matched_on": "manual-curated",
        "rename_to": "Sharon Jones & The Dap-Kings",
    },
    "New York Knicks": {
        "wikidata_id": "Q131364",
        "label": "New York Knicks",
        "description": "National Basketball Association team in New York City",
        "matched_on": "manual-curated",
    },
    "Penn and Teller": {
        "wikidata_id": "Q130317",
        "label": "Penn & Teller",
        "description": "American illusionists and entertainers",
        "matched_on": "manual-curated",
    },
    "Radiolab": {
        "wikidata_id": "Q2856080",
        "label": "Radiolab",
        "description": "American radio program",
        "matched_on": "manual-curated",
    },
    "Regina Carter": {
        "wikidata_id": "Q469383",
        "label": "Regina Carter",
        "description": "American jazz violinist",
        "matched_on": "manual-curated",
    },
    "Budos Band": {
        "wikidata_id": "Q3062682",
        "label": "The Budos Band",
        "description": "American musical group; instrumental band",
        "matched_on": "manual-curated",
        "rename_to": "The Budos Band",
    },
    "Akram Khan Company": {
        "wikidata_id": "Q54909558",
        "label": "Akram Khan Company",
        "description": "UK organization",
        "matched_on": "manual-curated",
    },
    "Joan Jett &amp; the Blackhearts": {
        "wikidata_id": "Q5931437",
        "label": "Joan Jett & the Blackhearts",
        "description": "American rock band",
        "matched_on": "manual-curated",
        "rename_to": "Joan Jett & the Blackhearts",
    },
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


def update_report(report_path: Path, old_name: str, new_name: str | None, payload: dict[str, str]) -> None:
    with report_path.open(newline="", encoding="utf-8") as handle:
        rows = list(csv.DictReader(handle))

    for row in rows:
        if row["name"] != old_name:
            continue
        if new_name:
            row["name"] = new_name
        row["status"] = "updated"
        row["wikidata_id"] = payload["wikidata_id"]
        row["label"] = payload["label"]
        row["description"] = payload["description"]
        row["matched_on"] = payload["matched_on"]

    with report_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=rows[0].keys())
        writer.writeheader()
        writer.writerows(rows)


def main() -> int:
    report_path = Path("staging/reports/artist-wikidata-backfill.csv")
    terms = wp_csv(["term", "list", "artist", "--fields=term_id,name,slug,count", "--format=csv"])
    terms_by_name = {term["name"]: term for term in terms}

    for old_name, payload in CURATED.items():
        term = terms_by_name.get(old_name)
        if not term:
            continue

        term_id = term["term_id"]
        new_name = payload.get("rename_to")

        if new_name and new_name != old_name:
            run_wp(["term", "update", "artist", term_id, f"--name={new_name}"])

        run_wp(["term", "meta", "update", term_id, "wikidata_id", payload["wikidata_id"]])
        update_report(report_path, old_name, new_name, payload)

        if old_name == "Bjork" and new_name == "Björk":
            posts = wp_csv(["post", "list", "--post_type=collection_item", "--posts_per_page=-1", "--search=Bjork", "--fields=ID,post_title", "--format=csv"])
            for post in posts:
                title = post["post_title"]
                updated_title = title.replace("Bjork", "Björk")
                if updated_title != title:
                    run_wp(["post", "update", post["ID"], f"--post_title={updated_title}"])

    print(report_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
