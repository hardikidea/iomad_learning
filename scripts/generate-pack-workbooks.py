#!/usr/bin/env python3
"""Generate review XLSX and LibreOffice ODS workbooks from canonical pack CSVs."""

from __future__ import annotations

import csv
import sys
import zipfile
from pathlib import Path
from xml.sax.saxutils import escape

import openpyxl


def parse_manifest(pack_dir: Path) -> dict[str, str]:
    files: dict[str, str] = {}
    in_files = False
    for line in (pack_dir / "manifest.yml").read_text(encoding="utf-8").splitlines():
        if line.strip() == "files:":
            in_files = True
            continue
        if in_files and line and not line.startswith(" "):
            in_files = False
        if in_files and ":" in line:
            key, value = line.strip().split(":", 1)
            files[key.strip()] = value.strip()
    return files


def read_csv(path: Path) -> list[list[str]]:
    with path.open(newline="", encoding="utf-8-sig") as handle:
        return [row for row in csv.reader(handle)]


def generate_xlsx(pack_dir: Path, output: Path) -> None:
    workbook = openpyxl.Workbook()
    workbook.remove(workbook.active)
    for entity, filename in parse_manifest(pack_dir).items():
        sheet = workbook.create_sheet(entity[:31])
        rows = read_csv(pack_dir / filename)
        for row in rows:
            sheet.append(row)
        if rows:
            sheet.freeze_panes = "A2"
            sheet.auto_filter.ref = sheet.dimensions
            for column in sheet.columns:
                width = max(len(str(cell.value or "")) for cell in column)
                sheet.column_dimensions[column[0].column_letter].width = min(max(width + 2, 12), 48)
    output.parent.mkdir(parents=True, exist_ok=True)
    workbook.save(output)


def ods_cell(value: str) -> str:
    return (
        '<table:table-cell office:value-type="string">'
        f"<text:p>{escape(value)}</text:p>"
        "</table:table-cell>"
    )


def generate_ods(pack_dir: Path, output: Path) -> None:
    tables = []
    for entity, filename in parse_manifest(pack_dir).items():
        rows = read_csv(pack_dir / filename)
        table_rows = []
        for row in rows:
            table_rows.append("<table:table-row>" + "".join(ods_cell(value) for value in row) + "</table:table-row>")
        tables.append(f'<table:table table:name="{escape(entity)}">' + "".join(table_rows) + "</table:table>")

    content = f"""<?xml version="1.0" encoding="UTF-8"?>
<office:document-content
 xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
 xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
 xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
 office:version="1.3">
 <office:body><office:spreadsheet>{''.join(tables)}</office:spreadsheet></office:body>
</office:document-content>
"""
    styles = """<?xml version="1.0" encoding="UTF-8"?>
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.3"/>
"""
    manifest = """<?xml version="1.0" encoding="UTF-8"?>
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
 <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/>
 <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
 <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
 <manifest:file-entry manifest:full-path="Basic/Standard/Module1.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
"""
    macro = """<?xml version="1.0" encoding="UTF-8"?>
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Module1" script:language="StarBasic">
REM Operator-side validation placeholder. Never execute macros in production or CI.
Sub ValidateInstitutionPack
    MsgBox "Validate exported CSV files with scripts/validate-pack-files.sh before import."
End Sub
</script:module>
"""
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, "w") as archive:
        archive.writestr("mimetype", "application/vnd.oasis.opendocument.spreadsheet", compress_type=zipfile.ZIP_STORED)
        archive.writestr("content.xml", content)
        archive.writestr("styles.xml", styles)
        archive.writestr("META-INF/manifest.xml", manifest)
        archive.writestr("Basic/Standard/Module1.xml", macro)


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: scripts/generate-pack-workbooks.py institution-packs/school/sample", file=sys.stderr)
        return 1

    pack_dir = Path(sys.argv[1]).resolve()
    if not (pack_dir / "manifest.yml").is_file():
        print(f"Missing manifest.yml in {pack_dir}", file=sys.stderr)
        return 1

    output_dir = pack_dir / "workbooks"
    stem = pack_dir.name
    generate_xlsx(pack_dir, output_dir / f"{stem}-review.xlsx")
    generate_ods(pack_dir, output_dir / f"{stem}-operator.ods")
    print(f"Generated workbooks in {output_dir}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
