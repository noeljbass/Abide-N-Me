# Importing a Bible package on IONOS web hosting

The SSH login and SFTP login use the same host, port, and user:

```sh
ssh -p 22 u112139307@access961326731.webspace-data.io
sftp -P 22 u112139307@access961326731.webspace-data.io
```

After logging in, change to the application directory (replace the example with
its actual location) and discover which versioned PHP CLI binary IONOS exposes:

```sh
cd /path/to/FeedMySheep
command -v php
find /usr/bin -maxdepth 1 -type f -name 'php*-cli' -print
php -v
```

IONOS commonly provides versioned CLI binaries separately from the PHP version
selected for the website. Set `PHP` to the executable reported by the commands
above; for example, if `/usr/bin/php8.3-cli` exists:

```sh
PHP=/usr/bin/php8.3-cli
"$PHP" -v
```

Apply the translation migration using the database credentials from the local
configuration. Avoid putting a database password directly in shell history:

```sh
mysql -h DB_HOST -u DB_USER -p DB_NAME < database/migrations/007_web_c_translation.sql
```

Validate the pinned archive before writing anything, then run the transactional
import:

```sh
"$PHP" bin/import-bible.php --source=eng-web-c --validate-only > storage/reports/eng-web-c-validation.json
"$PHP" bin/import-bible.php --source=eng-web-c
```

If `php` already resolves to the required CLI version, the shorter equivalent is
`php bin/import-bible.php ...`. The importer expects the archive at
`storage/imports/eng-web-c_usfm.zip` and refuses it if its SHA-256 differs from
the manifest.

## Importing the Berean Standard Bible

The BSB follows the same workflow, but uses the existing Protestant canon from
migration 006 and its own translation record from migration 009:

1. Deploy the updated application files, including
   `database/bible-sources.json`, the importer, and migration 009.
2. Upload `engbsb_usfm.zip` to `storage/imports/` without extracting or renaming
   it. Do not place the archive in Git.
3. From the application root, verify the uploaded file against the pinned hash:

   ```sh
   sha256sum storage/imports/engbsb_usfm.zip
   ```

   It must print
   `c065fa11decc416071985692b959dbd49506b188dc354e37805941f248b3da9e`.
4. Apply migrations in numeric order. If migration 006 is already recorded,
   only the new migration needs to run:

   ```sh
   mysql -h DB_HOST -u DB_USER -p DB_NAME < database/migrations/009_bsb_translation.sql
   ```

5. Validate the entire archive without changing the database, and review the
   report for 66 books and no errors:

   ```sh
   "$PHP" bin/import-bible.php --source=engbsb --validate-only > storage/reports/engbsb-validation.json
   ```

6. Run the transactional import:

   ```sh
   "$PHP" bin/import-bible.php --source=engbsb
   ```

   On success, the command reports the imported totals and activates BSB. If it
   fails, the database transaction is rolled back and BSB remains inactive.
7. Confirm BSB appears in the application's translation selector, then remove
   the uploaded ZIP from publicly reachable hosting. Retain the validation
   report and pinned manifest entry for provenance.
