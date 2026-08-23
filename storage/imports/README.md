# Local Bible imports

Place a downloaded `engDRA_usfm.zip`, `eng-kjv_usfm.zip`, or
`engbsb_usfm.zip` package in this directory before running the command-line importer. Import archives are ignored
by Git and must not be served or committed. On production hosting, keep the
package outside the public web root whenever possible and delete it after a
verified import.

Every archive must have its SHA-256 recorded in `database/bible-sources.json`.
The importer deliberately refuses an unpinned or mismatched package. Import the
KJV after applying migration 006 with:

```sh
php bin/import-bible.php --source=eng-kjv --validate-only
php bin/import-bible.php --source=eng-kjv
```

The retained eBible KJV package also contains deuterocanonical and other
supplemental books. The KJV manifest declares those identifiers in
`excluded_book_codes`, so their omission from the Protestant 66-book import is
explicit and auditable. Any chapter-bearing identifier that is neither in the
canon nor that exclusion list still causes inspection to fail.

Import the Berean Standard Bible after applying migration 009 with:

```sh
php bin/import-bible.php --source=engbsb --validate-only
php bin/import-bible.php --source=engbsb
```

Like the KJV, the BSB uses the Protestant 66-book canon. Unlike the retained
KJV archive, the BSB archive contains only those 66 canonical books, so it does
not need an `excluded_book_codes` list.

Import the Catholic Edition of the World English Bible after applying migration
007:

```sh
php bin/import-bible.php --source=eng-web-c --validate-only
php bin/import-bible.php --source=eng-web-c
```

Its USFM identifiers `ESG` and `DAG` are mapped to this application's canonical
`EST` and `DAN` records while their provider identifiers remain in the import
metadata. See `docs/ionos-bible-import.md` for IONOS SSH, PHP CLI, migration, and
production import commands.
