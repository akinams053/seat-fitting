#!/usr/bin/env python3
"""
Extract zh (Tranquility / 国际服) translations from CCP SDE YAML files
into a single JSON for the plugin's translation import.

Inputs: `fsd/{types,groups,categories,marketGroups}.yaml` from a CCP SDE
extraction (e.g., the contents of `sde.zip` unzipped under <sde_dir>).
The CCP SDE files are line-structured per top-level numeric ID with
8-language `name:` blocks; we don't need a real YAML parser — a small
stateful line scanner is enough and avoids loading 146MB into memory.

Output JSON shape:
  {
    "invTypes":        {"587": "锐影", ...},
    "invGroups":       {...},
    "invCategories":   {...},
    "invMarketGroups": {...}
  }

Usage:
    python3 extract-sde-translations.py <sde_dir> <output_json>
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ID_RE = re.compile(r"^(\d+):\s*$")
ZH_RE = re.compile(r"^    zh:\s+(.*)$")
INDENT_FIELD = "    "  # 4 spaces = inside the name/nameID block


def _unquote(raw: str) -> str:
    """Strip YAML single/double quotes if present; unescape minimal forms."""
    s = raw.rstrip()
    if not s:
        return s
    if s[0] == "'" and s[-1] == "'":
        return s[1:-1].replace("''", "'")
    if s[0] == '"' and s[-1] == '"':
        return s[1:-1].encode("utf-8").decode("unicode_escape")
    return s


def extract_zh(yaml_path: Path, name_field: str) -> dict[str, str]:
    """Stream a CCP SDE YAML, emit `{id: zh_name}` from each entry's localized name block.

    Most SDE files store the localized name under `name:` (types, groups, categories).
    `marketGroups.yaml` uses `nameID:` instead — and additionally carries a
    `descriptionID:` block (also localized) that we MUST NOT pick up, so the caller
    explicitly names which field to track.
    """
    name_re = re.compile(rf"^  {re.escape(name_field)}:\s*$")
    out: dict[str, str] = {}
    current_id: str | None = None
    in_name_block = False

    with yaml_path.open("r", encoding="utf-8") as fh:
        for line in fh:
            m = ID_RE.match(line)
            if m:
                current_id = m.group(1)
                in_name_block = False
                continue
            if current_id is None:
                continue
            if name_re.match(line):
                in_name_block = True
                continue
            if in_name_block:
                m = ZH_RE.match(line)
                if m:
                    out[current_id] = _unquote(m.group(1))
                    in_name_block = False
                elif not line.startswith(INDENT_FIELD):
                    # Left the name block without finding zh — entry has no zh translation
                    in_name_block = False
    return out


def main() -> int:
    if len(sys.argv) != 3:
        sys.stderr.write(__doc__ or "")
        return 2

    sde_dir = Path(sys.argv[1])
    out_path = Path(sys.argv[2])

    # (file, which localized-name field that file uses)
    sources = {
        "invTypes": (sde_dir / "fsd" / "types.yaml", "name"),
        "invGroups": (sde_dir / "fsd" / "groups.yaml", "name"),
        "invCategories": (sde_dir / "fsd" / "categories.yaml", "name"),
        "invMarketGroups": (sde_dir / "fsd" / "marketGroups.yaml", "nameID"),
    }

    bundle: dict[str, dict[str, str]] = {}
    for key, (path, field) in sources.items():
        if not path.exists():
            sys.stderr.write(f"missing: {path}\n")
            return 1
        data = extract_zh(path, field)
        sys.stderr.write(f"{key:18s} {len(data):>6d} entries from {path.name}\n")
        bundle[key] = data

    # Compact JSON (no indent) keeps repo size down while staying diff-friendly.
    # Keys are stringified IDs to match JSON object semantics; consumers cast.
    out_path.write_text(
        json.dumps(bundle, ensure_ascii=False, separators=(",", ":")),
        encoding="utf-8",
    )
    sys.stderr.write(f"wrote {out_path} ({out_path.stat().st_size:,} bytes)\n")
    return 0


if __name__ == "__main__":
    sys.exit(main())
