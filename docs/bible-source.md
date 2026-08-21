# Initial Bible source: Douay-Rheims 1899 American Edition

## Selected source

The initial translation identifier is **eBible.org `engDRA`**, described by the
publisher as the Douay-Rheims 1899 American Edition and marked **Public Domain**.

- Official translation page: <https://ebible.org/Scriptures/details.php?id=engDRA>
- Developer formats: <https://ebible.org/Scriptures/details.php?id=engDRA&all=1>
- Zipped USFM package: <https://ebible.org/Scriptures/engDRA_usfm.zip>
- Official machine-readable package directory: <https://ebible.org/Scriptures/>
- Independent public-domain text reference: Project Gutenberg ebook 8300,
  <https://www.gutenberg.org/ebooks/8300>

eBible.org is selected over copying an arbitrary GitHub dataset because it
publishes machine-readable Bible packages, identifies the translation, and
states the rights status on the translation page. Project Gutenberg is retained
as an independent provenance check, not as the automated import source.

## Import gate

Bible text is deliberately **not committed in this iteration**. Before import:

1. Open the official **Show formats for developers** page above and download
   `engDRA_usfm.zip` from the USFM row. The expected direct URL is the zipped
   USFM package link recorded above.
2. Save the download date, exact URL, file name, and SHA-256 checksum.
3. Confirm the downloaded package still says `Public Domain` and identifies
   itself as `engDRA` / Douay-Rheims 1899 American Edition.
4. Verify that all 73 books are present and manually inspect Tobit, Judith,
   Wisdom, Sirach, Baruch, 1–2 Maccabees, Esther 10–16, and Daniel 13–14.
5. Validate the source numbering before mapping verses. Douay/Vulgate Psalm and
   book names must be mapped to the app's canonical identifiers rather than
   silently assumed to match another provider.
6. Record the provenance and checksum with the eventual import migration.

No source API key is required. Provider metadata remains inactive until a
verified package passes these checks and is imported.

## Retained package provenance

The project owner downloaded the official package and retained the following
provenance independently of the repository:

| Field | Value |
| --- | --- |
| Filename | `engDRA_usfm.zip` |
| Download URL | `https://ebible.org/Scriptures/engDRA_usfm.zip` |
| Downloaded at | `2026-08-21 12:03:30` (timezone not recorded) |
| SHA-256 | `b891705afb54aac46b98d9b87320620fde7cab9cf6d1fc5459d2785d2df5cd94` |

The archive itself is intentionally not committed or uploaded to the public web
root. The future importer must calculate the archive checksum before extracting
anything and refuse the import unless it exactly matches the retained SHA-256.
