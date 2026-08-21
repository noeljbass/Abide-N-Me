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
