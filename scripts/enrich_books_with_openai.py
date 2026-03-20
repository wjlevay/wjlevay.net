#!/usr/bin/env python3
"""
Enrich a book-oriented review CSV using OpenAI image analysis.

Reads a review CSV plus processed cover images and writes an import-ready CSV
with improved title, author/agent, publisher, year/date, and format metadata.
"""

from __future__ import annotations

import argparse
import base64
import csv
import json
import os
import re
import sys
from pathlib import Path
from typing import Any

import requests


OUTPUT_HEADERS = [
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
    "item_publisher",
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
    "conversion_status",
    "parse_notes",
    "review_status",
]


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


def guess_mime_type(path: Path) -> str:
    suffix = path.suffix.lower()
    if suffix == ".png":
        return "image/png"
    if suffix in {".jpg", ".jpeg"}:
        return "image/jpeg"
    raise ValueError(f"Unsupported image type: {path.suffix}")


def image_to_data_url(path: Path) -> str:
    mime_type = guess_mime_type(path)
    data = base64.b64encode(path.read_bytes()).decode("ascii")
    return f"data:{mime_type};base64,{data}"


def clean_json_payload(text: str) -> dict[str, Any]:
    candidate = text.strip()
    match = re.search(r"\{.*\}", candidate, re.DOTALL)
    if match:
        candidate = match.group(0)
    return json.loads(candidate)


def extract_output_text(data: dict[str, Any]) -> str:
    output_text = data.get("output_text", "")
    if isinstance(output_text, str) and output_text.strip():
        return output_text.strip()

    outputs = data.get("output", [])
    if isinstance(outputs, list):
        for item in outputs:
            if not isinstance(item, dict) or item.get("type") != "message":
                continue
            for content in item.get("content", []):
                if not isinstance(content, dict):
                    continue
                text = content.get("text", "")
                if content.get("type") == "output_text" and isinstance(text, str) and text.strip():
                    return text.strip()

    raise ValueError("OpenAI response did not include extractable text output.")


def build_prompt(row: dict[str, str]) -> str:
    return f"""
You are enriching metadata for a photographed book cover or book object in a personal collection.

Return only valid JSON with these keys:
- title
- artists
- item_publisher
- item_year
- item_date_display
- item_sort_date
- item_materials
- subjects
- item_inscription
- parse_notes
- confidence

Rules:
- `title` should be the book title only, not title plus author.
- `artists` should be the author name as a pipe-delimited string. Use the fullest author form visible or confidently known.
- `item_publisher` should be the publisher or imprint if visible or confidently inferable from the edition.
- `item_year` must be a 4-digit publication year if visible or confidently inferable, otherwise blank.
- `item_date_display` should usually equal `item_year` for books.
- `item_sort_date` should be `YYYY-01-01` when a year is known, otherwise blank.
- `item_materials` should be a concise physical format like `Paperback` or `Hardcover`.
- `subjects` should be pipe-delimited topical terms only; do not repeat the title or author.
- `item_inscription` should stay brief. Only include notable edition/series/context details if useful.
- If you are unsure, preserve the existing parsed values rather than inventing specifics.
- `confidence` must be one of: high, medium, low.

Existing parsed values:
title: {row.get("title", "")}
artists: {row.get("artists", "")}
item_year: {row.get("item_year", "")}
item_date_display: {row.get("item_date_display", "")}
item_publisher: {row.get("item_publisher", "")}
item_materials: {row.get("item_materials", "")}
source_filename: {row.get("source_filename", "")}
parse_notes: {row.get("parse_notes", "")}
""".strip()


def analyze_row(row: dict[str, str], image_path: Path, api_key: str, model: str) -> dict[str, Any]:
    payload = {
        "model": model,
        "input": [
            {
                "role": "user",
                "content": [
                    {"type": "input_text", "text": build_prompt(row)},
                    {"type": "input_image", "image_url": image_to_data_url(image_path)},
                ],
            }
        ],
    }

    response = requests.post(
        "https://api.openai.com/v1/responses",
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        },
        json=payload,
        timeout=180,
    )
    response.raise_for_status()
    return clean_json_payload(extract_output_text(response.json()))


def normalize_pipe_list(value: str) -> str:
    parts = [part.strip() for part in value.split("|") if part.strip()]
    return "|".join(dict.fromkeys(parts))


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--csv", required=True)
    parser.add_argument("--images-base", required=True)
    parser.add_argument("--env-file", default=".env")
    parser.add_argument("--output")
    parser.add_argument("--model")
    parser.add_argument("--limit", type=int, default=0)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    env_values = load_env(Path(args.env_file))
    api_key = env_values.get("OPENAI_API_KEY") or os.environ.get("OPENAI_API_KEY")
    model = args.model or env_values.get("OPENAI_MODEL") or "gpt-5-mini"

    if not api_key:
        raise SystemExit("OPENAI_API_KEY not found in .env or environment.")

    csv_path = Path(args.csv).resolve()
    images_base = Path(args.images_base).resolve()
    output_path = Path(args.output).resolve() if args.output else csv_path.with_name(csv_path.stem + "-books-import.csv")

    with csv_path.open("r", newline="", encoding="utf-8") as handle:
        rows = list(csv.DictReader(handle))

    if args.limit > 0:
        rows = rows[: args.limit]

    output_rows: list[dict[str, str]] = []

    for index, row in enumerate(rows, start=1):
        featured_image = row.get("featured_image", "").strip()
        if not featured_image:
            continue

        image_path = (images_base / featured_image).resolve()
        if not image_path.exists():
            print(f"[skip] missing image: {featured_image}", file=sys.stderr)
            continue

        print(f"[{index}/{len(rows)}] {row.get('source_filename', '')}", file=sys.stderr)
        suggestion = analyze_row(row, image_path, api_key, model)

        title = str(suggestion.get("title", "")).strip() or row.get("title", "").strip()
        artists = normalize_pipe_list(str(suggestion.get("artists", "")).strip() or row.get("artists", "").strip())
        publisher = str(suggestion.get("item_publisher", "")).strip() or row.get("item_publisher", "").strip()
        item_year = re.sub(r"[^\d]", "", str(suggestion.get("item_year", "")).strip())[:4] or row.get("item_year", "").strip()
        item_date_display = str(suggestion.get("item_date_display", "")).strip() or (item_year if item_year else row.get("item_date_display", "").strip())
        item_sort_date = str(suggestion.get("item_sort_date", "")).strip() or (f"{item_year}-01-01" if item_year else row.get("item_sort_date", "").strip())
        item_materials = str(suggestion.get("item_materials", "")).strip() or row.get("item_materials", "").strip()
        subjects = normalize_pipe_list(str(suggestion.get("subjects", "")).strip() or row.get("subjects", "").strip())
        item_inscription = str(suggestion.get("item_inscription", "")).strip() or row.get("item_inscription", "").strip()
        parse_notes = " ".join(
            part
            for part in [
                row.get("parse_notes", "").strip(),
                str(suggestion.get("parse_notes", "")).strip(),
                f"OpenAI confidence: {str(suggestion.get('confidence', '')).strip()}".strip(),
            ]
            if part
        )

        output_rows.append(
            {
                "title": title,
                "content": row.get("content", ""),
                "excerpt": row.get("excerpt", ""),
                "collection": row.get("collection", ""),
                "artists": artists,
                "venue": row.get("venue", ""),
                "location": row.get("location", ""),
                "subjects": subjects,
                "item_identifier": row.get("item_identifier", ""),
                "item_sort_date": item_sort_date,
                "item_year": item_year,
                "item_date_display": item_date_display,
                "item_publisher": publisher,
                "item_condition": row.get("item_condition", ""),
                "item_materials": item_materials,
                "item_dimensions": row.get("item_dimensions", ""),
                "item_rights": row.get("item_rights", ""),
                "item_source": row.get("item_source", ""),
                "item_inscription": item_inscription,
                "item_event_link": row.get("item_event_link", ""),
                "item_dropbox_path": row.get("item_dropbox_path", ""),
                "featured_image": row.get("featured_image", ""),
                "gallery_images": row.get("gallery_images", ""),
                "source_filename": row.get("source_filename", ""),
                "conversion_status": row.get("conversion_status", ""),
                "parse_notes": parse_notes,
                "review_status": row.get("review_status", ""),
            }
        )

    output_path.parent.mkdir(parents=True, exist_ok=True)
    with output_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=OUTPUT_HEADERS)
        writer.writeheader()
        writer.writerows(output_rows)

    print(output_path)
    print(f"rows={len(output_rows)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
