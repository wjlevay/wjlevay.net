#!/usr/bin/env python3
"""
Build a manual-review CSV from a Dropbox shared folder ZIP.

Workflow:
1. Download the shared folder as a ZIP.
2. Extract source files into staging/raw.
3. Normalize source files into staging/processed:
   - TIFF/TIF -> JPG
   - PDF -> PNG (first page)
   - PNG/JPG/JPEG -> copied through
4. Parse filenames into best-effort metadata.
5. Write a review CSV for manual cleanup before WP ingest.
"""

from __future__ import annotations

import argparse
import csv
import re
import shutil
import subprocess
import sys
import zipfile
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Iterable

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
    "conversion_status",
    "parse_notes",
    "review_status",
]

NAME_OVERRIDES = {
    "76ers": "76ers",
    "10-Hairy-Legs": "10 Hairy Legs",
    "ABT-Nutcracker": "ABT Nutcracker",
    "Akoya-Afrobeat-Ensemble": "Akoya Afrobeat Ensemble",
    "Akram-Khan-Company": "Akram Khan Company",
    "All-Points-West": "All Points West",
    "Amsterjam": "AmsterJam",
    "Ari-Up": "Ari Up",
    "Atoms-For-Peace": "Atoms for Peace",
    "BB-King": "B.B. King",
    "Badfish": "Badfish",
    "Barrington-Levy": "Barrington Levy",
    "Beach-House": "Beach House",
    "Beauty-and-the-Beast": "Beauty and the Beast",
    "Blink-182": "Blink-182",
    "Bloodhound-Gang": "Bloodhound Gang",
    "Bob-Dylan": "Bob Dylan",
    "Book-of-Mormon": "Book of Mormon",
    "Bonobo": "Bonobo",
    "Boz-Scaggs": "Boz Scaggs",
    "Brian-Setzer-Orchestra": "Brian Setzer Orchestra",
    "Brian-Wilson": "Brian Wilson",
    "Brooklyn-Nets": "Brooklyn Nets",
    "Budos-Band": "Budos Band",
    "Burning-Spear": "Burning Spear",
    "Canadian-Brass": "Canadian Brass",
    "Charles-Bradley": "Charles Bradley",
    "Cherry-Poppin-Daddies": "Cherry Poppin' Daddies",
    "Curious-Incident": "The Curious Incident",
    "DJ-Shadow-and-Cut-Chemist": "DJ Shadow and Cut Chemist",
    "DJ-Spooky": "DJ Spooky",
    "Dave-Matthews-Band": "Dave Matthews Band",
    "Death-of-a-Salesman": "Death of a Salesman",
    "Dr-John": "Dr. John",
    "Dum-Dum-Girls": "Dum Dum Girls",
    "EO-Wilson-at-AMNH": "E.O. Wilson at AMNH",
    "Easy-Sar-All-Stars": "Easy Star All-Stars",
    "El-Michaels-Affair": "El Michels Affair",
    "Elvis-Costello": "Elvis Costello",
    "Erykah-Badu": "Erykah Badu",
    "Face-to-Face": "Face to Face",
    "Fela": "Fela Kuti",
    "Femi-Kuti": "Femi Kuti",
    "Fenix-TX": "Fenix TX",
    "Fish-in-the-Dark": "Fish in the Dark",
    "Get-Up-Kids": "The Get Up Kids",
    "Golden-State-Warriors": "Golden State Warriors",
    "Goldfinger": "Goldfinger",
    "Gregory-Isaacs": "Gregory Isaacs",
    "Herbie-Hancock": "Herbie Hancock",
    "Hermans-Hermits": "Herman's Hermits",
    "House-of-Waters": "House of Waters",
    "Impractical-Jokers": "Impractical Jokers",
    "Junior-Boys": "Junior Boys",
    "Joe-Jackson": "Joe Jackson",
    "Jay-Z": "Jay-Z",
    "Jessie-Ware": "Jessie Ware",
    "Kamasi-Washington": "Kamasi Washington",
    "Kool-Keith": "Kool Keith",
    "Korn": "Korn",
    "Koyaanisqatsi": "Koyaanisqatsi",
    "Kurt-Vile": "Kurt Vile",
    "LAPhil": "LA Phil",
    "LCD-Soundsystem": "LCD Soundsystem",
    "Lee-Scratch-Perry": "Lee Scratch Perry",
    "Le-Tigre": "Le Tigre",
    "Les-Savy-Fav": "Les Savy Fav",
    "Less-Than-Jake": "Less Than Jake",
    "Limp-Bizkit": "Limp Bizkit",
    "Louis-CK": "Louis C.K.",
    "M.I.A": "M.I.A.",
    "Maceo-Parker": "Maceo Parker",
    "Maynard-Ferguson": "Maynard Ferguson",
    "Menahan-Street-Band": "Menahan Street Band",
    "Meredith-Monk": "Meredith Monk",
    "Michael-Brecker": "Michael Brecker",
    "Milton-Henry": "Milton Henry",
    "Mulatu-Astatke": "Mulatu Astatke",
    "N.E.R.D.": "N.E.R.D.",
    "NY-Jets": "NY Jets",
    "NY-Knicks": "NY Knicks",
    "NY-Mets": "NY Mets",
    "NY-Rangers": "NY Rangers",
    "NY-Yankees": "NY Yankees",
    "NYPhil": "NY Phil",
    "NYPhil-Brass": "NY Phil Brass",
    "Nederlands-Dans-Theatre-2": "Nederlands Dans Theatre 2",
    "New-Found-Glory": "New Found Glory",
    "New-Yorker": "New Yorker",
    "Nine-Inch-Nails": "Nine Inch Nails",
    "Nickel-Creek": "Nickel Creek",
    "O.A.R.": "O.A.R.",
    "One-Night-of-Queen": "One Night of Queen",
    "Opry-at-the-Ryman": "Opry at the Ryman",
    "PSU-Lee-Konitz-Trio": "PSU Lee Konitz Trio",
    "Paragraph": "Paragraph",
    "Patricia-Barber": "Patricia Barber",
    "Patton-Oswalt": "Patton Oswalt",
    "Penn-Sate": "Penn State",
    "Penn-State": "Penn State",
    "Penn-and-Teller": "Penn and Teller",
    "Pee-Wee-Herman-Show": "Pee-wee Herman Show",
    "Philip-Glass": "Philip Glass",
    "Phosphorescent": "Phosphorescent",
    "Pietasters": "The Pietasters",
    "Pilfers": "Pilfers",
    "Prairie-Home-Companion": "Prairie Home Companion",
    "Prarie-Home-Companion": "Prairie Home Companion",
    "Radiohead": "Radiohead",
    "Radiolab": "Radiolab",
    "Rain": "Rain",
    "Redacted": "Redacted",
    "Reel-Big-Fish": "Reel Big Fish",
    "Reggie-Wilson": "Reggie Wilson",
    "Regina-Carter": "Regina Carter",
    "Robyn": "Robyn",
    "Rock-the-Harbor": "Rock the Harbor",
    "Roy-Hargrove": "Roy Hargrove",
    "Rufus-Wainwright": "Rufus Wainwright",
    "SI-Yankees": "SI Yankees",
    "Salute-to-Music": "Salute to Music",
    "Sarah-Silverman": "Sarah Silverman",
    "Scientist": "Scientist",
    "Sharon-Jones": "Sharon Jones",
    "Shen-Wei": "Shen Wei",
    "Silverchair": "Silverchair",
    "Skunk-Anansie": "Skunk Anansie",
    "Skanatra": "Skanatra",
    "Solange": "Solange",
    "Sonny-Rollins": "Sonny Rollins",
    "Springsteen": "Bruce Springsteen",
    "Sugar-Minott": "Sugar Minott",
    "Sweeney-Todd": "Sweeney Todd",
    "TV-On-The-Radio": "TV on the Radio",
    "The-Streets": "The Streets",
    "The-Roots": "The Roots",
    "The-Younger": "The Younger",
    "Theophilus-London": "Theophilus London",
    "Thundercat": "Thundercat",
    "Ticklah": "Ticklah",
    "Toasters": "The Toasters",
    "Toots-and-the-Maytals": "Toots and the Maytals",
    "TV-On-The-Radio": "TV on the Radio",
    "Tycho": "Tycho",
    "War-On-Drugs": "The War on Drugs",
    "Weezer": "Weezer",
    "Whats-Your-Problem-Brian": "What's Your Problem, Brian?",
    "Wild-Belle": "Wild Belle",
    "Xiu-Xiu-plays-Twin-Peaks": "Xiu Xiu Plays Twin Peaks",
    "Zvi-Dance": "Zvi Dance",
    "ZviDance-Gala": "Zvi Dance Gala",
    "iOU-Dance-Solo-Series": "iOU Dance Solo Series",
}

NON_ARTIST_SEGMENTS = {
    "Concert-List",
    "Gala",
    "Koyaanisqatsi",
    "New-Yorker",
    "Redacted",
}


@dataclass
class ParsedMetadata:
    title: str
    artists: str
    item_sort_date: str
    item_year: str
    item_date_display: str
    parse_notes: str


def run(cmd: list[str]) -> None:
    subprocess.run(cmd, check=True)


def ensure_command(name: str) -> None:
    if shutil.which(name):
        return
    raise SystemExit(f"Required command not found: {name}")


def download_zip(url: str, destination: Path) -> Path:
    destination.parent.mkdir(parents=True, exist_ok=True)
    with requests.get(url, allow_redirects=True, stream=True, timeout=300) as response:
        response.raise_for_status()
        with destination.open("wb") as handle:
            for chunk in response.iter_content(chunk_size=1024 * 1024):
                if chunk:
                    handle.write(chunk)
    return destination


def extract_zip(zip_path: Path, raw_dir: Path) -> list[Path]:
    raw_dir.mkdir(parents=True, exist_ok=True)
    files: list[Path] = []
    with zipfile.ZipFile(zip_path) as archive:
        for entry in archive.infolist():
            if entry.is_dir():
                continue
            name = Path(entry.filename).name
            if not name or name.startswith("."):
                continue
            target = raw_dir / name
            with archive.open(entry, "r") as source, target.open("wb") as dest:
                shutil.copyfileobj(source, dest)
            files.append(target)
    return sorted(files)


def normalize_date_prefix(stem: str) -> tuple[str, str]:
    match = re.match(r"^(?P<date>\d{4}(?:[-_]\d{2}(?:-\d{2})?)?)_(?P<label>.+)$", stem)
    if match:
        raw_date = match.group("date").replace("_", "-")
        return raw_date, match.group("label")

    match = re.match(r"^(?P<year>\d{4})-(?P<label>.+)$", stem)
    if match:
        return match.group("year"), match.group("label")

    return "", stem


def humanize_name(fragment: str) -> str:
    if fragment in NAME_OVERRIDES:
        return NAME_OVERRIDES[fragment]

    cleaned = fragment.replace("_", " ").strip()
    if not cleaned:
        return cleaned

    parts = cleaned.split("-")
    human_parts: list[str] = []
    for part in parts:
        if part in NAME_OVERRIDES:
            human_parts.append(NAME_OVERRIDES[part])
            continue
        if "." in part or any(ch.isdigit() for ch in part):
            human_parts.append(part)
            continue
        human_parts.append(part.title())
    return " ".join(human_parts).replace(" And ", " and ")


def parse_date(raw_date: str) -> tuple[str, str, list[str]]:
    notes: list[str] = []
    raw_date = raw_date.strip()
    if not raw_date:
        notes.append("No date parsed from filename.")
        return "", "", notes

    normalized = raw_date.replace("_", "-")

    if re.fullmatch(r"\d{4}-\d{2}-\d{2}", normalized):
        dt = datetime.strptime(normalized, "%Y-%m-%d")
        display = f"{dt.strftime('%B')} {dt.day}, {dt.year}"
        return normalized, str(dt.year), notes

    if re.fullmatch(r"\d{4}-\d{2}", normalized):
        sort_date = f"{normalized}-01"
        notes.append("Month-level date inferred to first day of month for sorting.")
        year = normalized[:4]
        return sort_date, year, notes

    if re.fullmatch(r"\d{4}", normalized):
        sort_date = f"{normalized}-01-01"
        return sort_date, normalized, notes

    notes.append("Filename date did not match expected patterns.")
    return "", "", notes


def build_display_date(sort_date: str, raw_date: str) -> str:
    if re.fullmatch(r"\d{4}-\d{2}-\d{2}", raw_date.replace("_", "-")):
        dt = datetime.strptime(sort_date, "%Y-%m-%d")
        return f"{dt.strftime('%B')} {dt.day}, {dt.year}"
    if re.fullmatch(r"\d{4}", raw_date.replace("_", "-")):
        return raw_date[:4]
    if re.fullmatch(r"\d{4}-\d{2}", raw_date.replace("_", "-")):
        year, month = raw_date.replace("_", "-").split("-")
        dt = datetime.strptime(f"{year}-{month}-01", "%Y-%m-%d")
        return dt.strftime("%B %Y")
    return raw_date


def parse_filename(stem: str) -> ParsedMetadata:
    notes: list[str] = []
    raw_date, raw_label = normalize_date_prefix(stem)
    sort_date, item_year, date_notes = parse_date(raw_date)
    notes.extend(date_notes)
    item_date_display = build_display_date(sort_date, raw_date) if raw_date else ""

    raw_segments = [segment for segment in raw_label.split("_") if segment]
    if not raw_segments:
        raw_segments = [raw_label] if raw_label else []

    title_segments = [humanize_name(segment) for segment in raw_segments]
    title = " / ".join([segment for segment in title_segments if segment]) or stem

    artist_segments: list[str] = []
    for segment in raw_segments:
        if segment in NON_ARTIST_SEGMENTS:
            notes.append(f"Review artist parsing for segment: {segment}.")
            continue
        if segment == "Concert-List":
            continue
        artist_segments.append(humanize_name(segment))

    artists = "|".join([segment for segment in artist_segments if segment])

    if "_" in raw_label:
        notes.append("Multiple filename segments detected; review title and artist fields.")
    if "at-" in raw_label.lower():
        notes.append("Filename appears to include venue/context in title segment.")
    if raw_label.lower().endswith("redacted"):
        notes.append("Filename indicates redacted image.")
    if raw_label.lower().endswith("new-yorker"):
        notes.append("Filename likely represents a publication clipping or insert.")
    if raw_label.lower() == "concert-list":
        notes.append("Generic title inferred from filename; review required.")

    if not artists:
        notes.append("No artist inferred from filename.")

    return ParsedMetadata(
        title=title,
        artists=artists,
        item_sort_date=sort_date,
        item_year=item_year,
        item_date_display=item_date_display,
        parse_notes=" ".join(dict.fromkeys(notes)),
    )


def convert_file(source: Path, processed_dir: Path) -> tuple[str, str]:
    processed_dir.mkdir(parents=True, exist_ok=True)
    suffix = source.suffix.lower()
    stem = source.stem

    if suffix in {".png", ".jpg", ".jpeg"}:
        target = processed_dir / f"{stem}{suffix if suffix != '.jpeg' else '.jpg'}"
        shutil.copy2(source, target)
        return str(Path("processed") / target.name), "copied"

    if suffix in {".tif", ".tiff"}:
        ensure_command("convert")
        target = processed_dir / f"{stem}.jpg"
        run(["convert", str(source), "-auto-orient", "-strip", "-quality", "92", str(target)])
        return str(Path("processed") / target.name), "converted:tiff->jpg"

    if suffix == ".pdf":
        ensure_command("gs")
        target = processed_dir / f"{stem}.png"
        run(
            [
                "gs",
                "-dSAFER",
                "-dBATCH",
                "-dNOPAUSE",
                "-sDEVICE=png16m",
                "-r200",
                "-dFirstPage=1",
                "-dLastPage=1",
                f"-sOutputFile={target}",
                str(source),
            ]
        )
        return str(Path("processed") / target.name), "converted:pdf->png"

    raise ValueError(f"Unsupported source type: {source.suffix}")


def build_rows(files: Iterable[Path], collection: str, item_source: str, dropbox_root: str, processed_dir: Path) -> list[dict[str, str]]:
    rows: list[dict[str, str]] = []

    for source in files:
        suffix = source.suffix.lower()
        if suffix not in {".png", ".jpg", ".jpeg", ".tif", ".tiff", ".pdf"}:
            continue

        parsed = parse_filename(source.stem)
        featured_image, conversion_status = convert_file(source, processed_dir)
        item_identifier = f"dropbox-{collection.lower().replace(' ', '-').replace('&', 'and')}-{source.stem.lower()}"

        rows.append(
            {
                "title": parsed.title,
                "content": "",
                "excerpt": "",
                "collection": collection,
                "artists": parsed.artists,
                "venue": "",
                "location": "",
                "subjects": "",
                "item_identifier": item_identifier,
                "item_sort_date": parsed.item_sort_date,
                "item_year": parsed.item_year,
                "item_date_display": parsed.item_date_display,
                "item_condition": "",
                "item_materials": "",
                "item_dimensions": "",
                "item_rights": "",
                "item_source": item_source,
                "item_inscription": "",
                "item_event_link": "",
                "item_dropbox_path": f"{dropbox_root}/{source.name}" if dropbox_root else source.name,
                "featured_image": featured_image,
                "gallery_images": "",
                "source_filename": source.name,
                "conversion_status": conversion_status,
                "parse_notes": parsed.parse_notes,
                "review_status": "",
            }
        )

    rows.sort(key=lambda row: (row["item_sort_date"] or "9999-99-99", row["title"]))
    return rows


def write_csv(rows: list[dict[str, str]], csv_path: Path) -> None:
    csv_path.parent.mkdir(parents=True, exist_ok=True)
    with csv_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=CSV_HEADERS)
        writer.writeheader()
        for row in rows:
            writer.writerow(row)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dropbox-url", required=True)
    parser.add_argument("--collection", default="Ticket Stubs & Flyers")
    parser.add_argument("--slug", default="ticket-stubs-and-flyers")
    parser.add_argument("--output-dir", default="staging/dropbox-review")
    parser.add_argument("--item-source", default="Dropbox shared folder")
    parser.add_argument("--dropbox-root", default="Ticket Stubs & Flyers")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    root = Path(args.output_dir).resolve() / args.slug
    zip_path = root / "download" / f"{args.slug}.zip"
    raw_dir = root / "raw"
    processed_dir = root / "processed"
    review_csv = root / "review" / f"{args.slug}-review.csv"

    print(f"Downloading Dropbox ZIP to {zip_path}", file=sys.stderr)
    download_zip(args.dropbox_url, zip_path)

    print(f"Extracting ZIP into {raw_dir}", file=sys.stderr)
    files = extract_zip(zip_path, raw_dir)

    print(f"Processing {len(files)} source files", file=sys.stderr)
    rows = build_rows(files, args.collection, args.item_source, args.dropbox_root, processed_dir)

    print(f"Writing review CSV to {review_csv}", file=sys.stderr)
    write_csv(rows, review_csv)

    print(review_csv)
    print(f"rows={len(rows)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
