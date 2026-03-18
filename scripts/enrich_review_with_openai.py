#!/usr/bin/env python3
"""
Enrich a Dropbox review CSV using OpenAI multimodal analysis.

This script reads:
- a review-stage CSV produced by build_dropbox_review.py
- the processed image files referenced by `featured_image`
- OPENAI_API_KEY from a local .env file

It writes a second CSV with suggested metadata fields for human review.
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
    "source_filename",
    "featured_image",
    "title",
    "artists",
    "venue",
    "location",
    "subjects",
    "item_sort_date",
    "item_date_display",
    "suggested_title",
    "suggested_artists",
    "suggested_venue",
    "suggested_location",
    "suggested_subjects",
    "suggested_item_date_display",
    "suggested_item_inscription",
    "suggested_event_type",
    "suggested_parse_notes",
    "model_confidence",
    "needs_human_review",
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
    raise ValueError(f"Unsupported image type for OpenAI enrichment: {path.suffix}")


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
You are enriching metadata for an archival collection item image.

Return only valid JSON with these keys:
- suggested_title
- suggested_artists
- suggested_venue
- suggested_location
- suggested_subjects
- suggested_item_date_display
- suggested_item_inscription
- suggested_event_type
- suggested_parse_notes
- model_confidence
- needs_human_review

Rules:
- `suggested_title` must be in WordPress post-title style:
  - one artist: `Artist at Venue`
  - multiple artists: `Artist 1 / Artist 2 at Venue`
  - if the event title already includes the venue name naturally, do not append `at Venue` again
  - if venue cannot be determined confidently, fall back to the best concise event title
- `suggested_artists` and `suggested_subjects` must be pipe-delimited strings.
- Do not repeat artist/agent names in `suggested_subjects`.
- If the image is a sports ticket, include both teams in `suggested_artists` pipe-delimited.
- `suggested_location` should be a normalized place access point like "Manhattan, NY" or "East Rutherford, NJ", not a street address.
- `suggested_venue` should be the specific venue/building.
- `suggested_item_date_display` should be human-readable, not ISO.
- `suggested_item_inscription` should be brief and only include interesting show/context information such as tour names,
  festival names, branded event series, or unusual contextual details.
- Do not include seat numbers, row numbers, ticket prices, barcode/vendor boilerplate, or generic ticketing text.
- `model_confidence` must be one of: high, medium, low.
- `needs_human_review` must be true or false.
- If uncertain, preserve the existing parsed values rather than inventing details.
- If the image appears to be a supplemental clipping, insert, article page, ad, or extra ephemera related to another event item, say so in `suggested_parse_notes`.

Existing parsed metadata:
title: {row.get("title", "")}
artists: {row.get("artists", "")}
venue: {row.get("venue", "")}
location: {row.get("location", "")}
subjects: {row.get("subjects", "")}
item_sort_date: {row.get("item_sort_date", "")}
item_date_display: {row.get("item_date_display", "")}
source_filename: {row.get("source_filename", "")}
parse_notes: {row.get("parse_notes", "")}
""".strip()


def analyze_row(row: dict[str, str], image_path: Path, api_key: str, model: str) -> dict[str, Any]:
    prompt = build_prompt(row)
    payload = {
        "model": model,
        "input": [
            {
                "role": "user",
                "content": [
                    {"type": "input_text", "text": prompt},
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
    data = response.json()
    output_text = extract_output_text(data)
    return clean_json_payload(output_text)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--csv", required=True)
    parser.add_argument("--images-base", required=True)
    parser.add_argument("--env-file", default=".env")
    parser.add_argument("--output")
    parser.add_argument("--model")
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--only-review-notes", action="store_true")
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
    output_path = Path(args.output).resolve() if args.output else csv_path.with_name(csv_path.stem + "-openai-review.csv")

    with csv_path.open("r", newline="", encoding="utf-8") as handle:
        rows = list(csv.DictReader(handle))

    if args.only_review_notes:
        rows = [row for row in rows if row.get("parse_notes", "").strip()]

    if args.limit > 0:
        rows = rows[: args.limit]

    enriched_rows: list[dict[str, str]] = []

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

        enriched_row = {key: row.get(key, "") for key in OUTPUT_HEADERS}
        enriched_row.update(
            {
                "source_filename": row.get("source_filename", ""),
                "featured_image": featured_image,
                "title": row.get("title", ""),
                "artists": row.get("artists", ""),
                "venue": row.get("venue", ""),
                "location": row.get("location", ""),
                "subjects": row.get("subjects", ""),
                "item_sort_date": row.get("item_sort_date", ""),
                "item_date_display": row.get("item_date_display", ""),
                "suggested_title": str(suggestion.get("suggested_title", "")),
                "suggested_artists": str(suggestion.get("suggested_artists", "")),
                "suggested_venue": str(suggestion.get("suggested_venue", "")),
                "suggested_location": str(suggestion.get("suggested_location", "")),
                "suggested_subjects": str(suggestion.get("suggested_subjects", "")),
                "suggested_item_date_display": str(suggestion.get("suggested_item_date_display", "")),
                "suggested_item_inscription": str(suggestion.get("suggested_item_inscription", "")),
                "suggested_event_type": str(suggestion.get("suggested_event_type", "")),
                "suggested_parse_notes": str(suggestion.get("suggested_parse_notes", "")),
                "model_confidence": str(suggestion.get("model_confidence", "")),
                "needs_human_review": str(suggestion.get("needs_human_review", "")),
            }
        )
        enriched_rows.append(enriched_row)

    output_path.parent.mkdir(parents=True, exist_ok=True)
    with output_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=OUTPUT_HEADERS)
        writer.writeheader()
        writer.writerows(enriched_rows)

    print(output_path)
    print(f"rows={len(enriched_rows)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
