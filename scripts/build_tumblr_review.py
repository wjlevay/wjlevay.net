#!/usr/bin/env python3
"""
Build a manual-review CSV from a public Tumblr blog.

Workflow:
1. Read Tumblr API credentials from .env.
2. Page through public blog posts.
3. Download highest-resolution images into staging/processed.
4. Parse dates and tags into review-ready metadata.
5. Write a review CSV for manual cleanup before WP ingest.
"""

from __future__ import annotations

import argparse
import csv
import json
import re
import sys
from collections import Counter
from datetime import datetime
from pathlib import Path
from typing import Any

import requests


CSV_HEADERS = [
    "title",
    "content",
    "excerpt",
    "collection",
    "artists",
    "venue",
    "location",
    "subjects",
    "item_identifier",
    "item_sort_date",
    "item_year",
    "item_date_display",
    "item_condition",
    "item_materials",
    "item_dimensions",
    "item_rights",
    "item_source",
    "item_inscription",
    "item_event_link",
    "item_dropbox_path",
    "featured_image",
    "gallery_images",
    "source_filename",
    "tumblr_post_id",
    "tumblr_post_url",
    "tumblr_tags_raw",
    "object_type",
    "parse_notes",
    "review_status",
]

LOCATION_TAGS = {
    "alameda": "Alameda, CA",
    "berkeley": "Berkeley, CA",
    "manhattan": "Manhattan, NY",
    "oakland": "Oakland, CA",
    "sanfrancisco": "San Francisco, CA",
    "staten island": "Staten Island, NY",
    "statenisland": "Staten Island, NY",
}

OBJECT_TAGS = {
    "audiotape": "Audiotape",
    "cassette": "Cassette",
    "cd": "CD",
    "cdr": "CD-R",
    "cds": "CD",
    "earphones": "Earphones",
    "radio": "Radio",
    "speaker": "Speaker",
    "tape": "Audiotape",
}

COMBINED_TAGS = {
    "cd oakland": {"object_type": "CD", "location": "Oakland, CA"},
    "radio oakland": {"object_type": "Radio", "location": "Oakland, CA"},
    "speaker oakland": {"object_type": "Speaker", "location": "Oakland, CA"},
}

IGNORE_SUBJECT_TAGS = {
    "audiolitter",
}


def load_env(env_path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    if not env_path.exists():
        return values

    for raw_line in env_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--blog", default="audiolitter.tumblr.com")
    parser.add_argument("--slug", default="audio-litter")
    parser.add_argument("--collection", default="audiolitter")
    parser.add_argument("--output-dir", default="staging/tumblr-review")
    parser.add_argument("--env-file", default=".env")
    parser.add_argument("--limit", type=int, default=0)
    return parser.parse_args()


def fetch_posts(blog: str, api_key: str, limit: int = 0) -> list[dict[str, Any]]:
    posts: list[dict[str, Any]] = []
    offset = 0
    page_size = 20
    headers = {"User-Agent": "wjlevay-net-import/1.0"}

    while True:
        response = requests.get(
            f"https://api.tumblr.com/v2/blog/{blog}/posts",
            params={"api_key": api_key, "npf": "true", "limit": page_size, "offset": offset},
            headers=headers,
            timeout=30,
        )
        response.raise_for_status()
        batch = response.json()["response"]["posts"]
        if not batch:
            break

        posts.extend(batch)
        if limit and len(posts) >= limit:
            return posts[:limit]

        offset += len(batch)
        if len(batch) < page_size:
            break

    return posts


def write_post_dump(posts: list[dict[str, Any]], output_path: Path) -> None:
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(posts, indent=2), encoding="utf-8")


def normalize_tag(tag: str) -> str:
    cleaned = tag.replace("\u200b", "").strip().replace("_", " ")
    cleaned = re.sub(r"\s+", " ", cleaned)
    return cleaned


def split_tags(tags: list[str]) -> tuple[str, str, str, list[str]]:
    object_types: list[str] = []
    locations: list[str] = []
    subjects: list[str] = []
    notes: list[str] = []

    for raw_tag in tags:
        tag = normalize_tag(raw_tag)
        lower = tag.lower()

        if lower in COMBINED_TAGS:
            combo = COMBINED_TAGS[lower]
            object_types.append(combo["object_type"])
            locations.append(combo["location"])
            continue

        if lower in OBJECT_TAGS:
            object_types.append(OBJECT_TAGS[lower])
            continue

        if lower in LOCATION_TAGS:
            locations.append(LOCATION_TAGS[lower])
            continue

        if lower in IGNORE_SUBJECT_TAGS:
            continue

        subjects.append(tag)

    object_types = sorted(set(object_types))
    locations = sorted(set(locations))
    subjects = sorted(set(subjects))

    if not object_types:
        notes.append("No object type inferred from Tumblr tags.")
    if not locations:
        notes.append("No location inferred from Tumblr tags.")

    return (
        "|".join(object_types),
        "|".join(locations),
        "|".join(subjects),
        notes,
    )


def build_title(display_date: str, object_type: str, duplicate_count: int) -> tuple[str, list[str]]:
    notes: list[str] = []
    primary_object = object_type.split("|")[0] if object_type else ""

    if duplicate_count <= 1:
        return display_date, notes

    if primary_object:
        notes.append("Duplicate date disambiguated with object type.")
        return f"{display_date} — {primary_object}", notes

    notes.append("Duplicate date found without object type; review title.")
    return display_date, notes


def ensure_unique_title(
    title: str,
    seen_counts: dict[str, int],
) -> tuple[str, list[str]]:
    notes: list[str] = []
    occurrence = seen_counts.get(title, 0) + 1
    seen_counts[title] = occurrence

    if occurrence <= 1:
        return title, notes

    notes.append("Duplicate title disambiguated with sequence number.")
    return f"{title} ({occurrence})", notes


def extract_text_content(post: dict[str, Any]) -> str:
    parts: list[str] = []
    for block in post.get("content", []):
        if block.get("type") != "text":
            continue
        text = (block.get("text") or "").strip()
        if text:
            parts.append(text)
    return "\n\n".join(parts).strip()


def get_best_image_url(block: dict[str, Any]) -> str:
    media = block.get("media", [])
    if not media:
        return ""
    media = sorted(media, key=lambda item: item.get("width", 0), reverse=True)
    return str(media[0].get("url", "")).strip()


def download_image(url: str, destination: Path) -> Path:
    destination.parent.mkdir(parents=True, exist_ok=True)
    with requests.get(url, stream=True, timeout=60, headers={"User-Agent": "wjlevay-net-import/1.0"}) as response:
        response.raise_for_status()
        with destination.open("wb") as handle:
            for chunk in response.iter_content(chunk_size=1024 * 1024):
                if chunk:
                    handle.write(chunk)
    return destination


def build_rows(posts: list[dict[str, Any]], root: Path, collection: str) -> tuple[list[dict[str, str]], Counter]:
    processed_dir = root / "processed"
    rows: list[dict[str, str]] = []
    tag_counter: Counter = Counter()
    date_counts = Counter()
    title_counts: dict[str, int] = {}

    for post in posts:
        date_text = str(post.get("date", "")).strip()
        dt = datetime.strptime(date_text, "%Y-%m-%d %H:%M:%S GMT")
        date_counts[f"{dt.strftime('%B')} {dt.day}, {dt.year}"] += 1

    for post in posts:
        post_id = int(post["id"])
        post_url = str(post.get("post_url", "")).strip()
        date_text = str(post.get("date", "")).strip()
        dt = datetime.strptime(date_text, "%Y-%m-%d %H:%M:%S GMT")
        item_sort_date = dt.strftime("%Y-%m-%d")
        item_date_display = f"{dt.strftime('%B')} {dt.day}, {dt.year}"
        item_year = str(dt.year)

        tags = [normalize_tag(tag) for tag in post.get("tags", [])]
        for tag in tags:
            tag_counter[tag] += 1

        object_type, location, subjects, split_notes = split_tags(tags)
        title, title_notes = build_title(item_date_display, object_type, date_counts[item_date_display])
        title, unique_title_notes = ensure_unique_title(title, title_counts)
        caption = extract_text_content(post)

        image_urls = []
        for block in post.get("content", []):
            if block.get("type") != "image":
                continue
            image_url = get_best_image_url(block)
            if image_url:
                image_urls.append(image_url)

        featured_image = ""
        gallery_images: list[str] = []
        source_filenames: list[str] = []

        for index, image_url in enumerate(image_urls, start=1):
            suffix_match = re.search(r"\.(jpg|jpeg|png|gif)(?:$|\?)", image_url, re.IGNORECASE)
            suffix = "." + suffix_match.group(1).lower() if suffix_match else ".jpg"
            filename = f"{post_id}-{index}{suffix if suffix != '.jpeg' else '.jpg'}"
            local_path = processed_dir / filename
            download_image(image_url, local_path)
            relative = str(Path("processed") / filename)
            source_filenames.append(filename)
            if not featured_image:
                featured_image = relative
            else:
                gallery_images.append(relative)

        parse_notes = split_notes + title_notes + unique_title_notes
        if not image_urls:
            parse_notes.append("No downloadable image found in post content.")
        if len(image_urls) > 1:
            parse_notes.append("Multiple images found; review whether this should be one item or several.")

        rows.append(
            {
                "title": title,
                "content": caption,
                "excerpt": "",
                "collection": collection,
                "artists": "",
                "venue": "",
                "location": location.split("|")[0] if "|" in location else location,
                "subjects": subjects,
                "item_identifier": f"tumblr-{post_id}",
                "item_sort_date": item_sort_date,
                "item_year": item_year,
                "item_date_display": item_date_display,
                "item_condition": "",
                "item_materials": object_type,
                "item_dimensions": "",
                "item_rights": "",
                "item_source": "Tumblr import",
                "item_inscription": "",
                "item_event_link": post_url,
                "item_dropbox_path": "",
                "featured_image": featured_image,
                "gallery_images": "|".join(gallery_images),
                "source_filename": "|".join(source_filenames),
                "tumblr_post_id": str(post_id),
                "tumblr_post_url": post_url,
                "tumblr_tags_raw": "|".join(tags),
                "object_type": object_type,
                "parse_notes": " ".join(dict.fromkeys(parse_notes)),
                "review_status": "",
            }
        )

    rows.sort(key=lambda row: (row["item_sort_date"], row["tumblr_post_id"]))
    return rows, tag_counter


def write_csv(rows: list[dict[str, str]], csv_path: Path) -> None:
    csv_path.parent.mkdir(parents=True, exist_ok=True)
    with csv_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=CSV_HEADERS)
        writer.writeheader()
        writer.writerows(rows)


def write_tag_report(counter: Counter, report_path: Path) -> None:
    report_path.parent.mkdir(parents=True, exist_ok=True)
    with report_path.open("w", encoding="utf-8") as handle:
        for tag, count in counter.most_common():
            handle.write(f"{count}\t{tag}\n")


def main() -> int:
    args = parse_args()
    env = load_env(Path(args.env_file))
    api_key = env.get("TUMBLR_CONSUMER_KEY", "").strip()
    if not api_key:
        raise SystemExit("TUMBLR_CONSUMER_KEY not found in .env.")

    root = (Path(args.output_dir) / args.slug).resolve()
    raw_json = root / "raw" / f"{args.slug}-posts.json"
    review_csv = root / "review" / f"{args.slug}-review.csv"
    tag_report = root / "review" / f"{args.slug}-tag-report.txt"

    print(f"Fetching public Tumblr posts from {args.blog}", file=sys.stderr)
    posts = fetch_posts(args.blog, api_key, args.limit)
    print(f"Fetched {len(posts)} posts", file=sys.stderr)

    print(f"Writing raw Tumblr dump to {raw_json}", file=sys.stderr)
    write_post_dump(posts, raw_json)

    print("Downloading media and building review rows", file=sys.stderr)
    rows, tag_counter = build_rows(posts, root, args.collection)

    print(f"Writing review CSV to {review_csv}", file=sys.stderr)
    write_csv(rows, review_csv)

    print(f"Writing tag report to {tag_report}", file=sys.stderr)
    write_tag_report(tag_counter, tag_report)

    print(f"Done. rows={len(rows)} unique_tags={len(tag_counter)}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
