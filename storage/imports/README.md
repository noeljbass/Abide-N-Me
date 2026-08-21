# Local Bible imports

Place a downloaded `engDRA_usfm.zip` or `eng-kjv_usfm.zip` package in this
directory before running the command-line importer. Import archives are ignored
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
