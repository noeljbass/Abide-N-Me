# Database setup

`schema.sql` is the initial, empty application schema. It creates structure only;
Bible text and canon seed data are intentionally not included yet.

## IONOS import

1. Create a MySQL database in the IONOS Control Panel under **Hosting > Databases**.
2. Record the generated database host, database name, username, and password.
3. Open the database's phpMyAdmin action from IONOS.
4. Select the database, choose **Import**, upload `schema.sql`, retain UTF-8, and run the import.
5. Confirm that `schema_migrations` contains `001_database_foundation`.

Do not upload private database configuration to source control. Prefer IONOS
environment variables when available; otherwise copy `config/config.example.php`
to either `config/local.php` or `config/config.php` on the server and replace every
placeholder there. Editing `config.example.php` itself does not configure the app.

After configuration, open `/api/health.php`. A ready installation returns
`database: connected` and `schema: ready`. A safe error code identifies a missing
private configuration, failed connection, or incomplete authentication schema
without exposing credentials or SQL errors.

## Iteration migrations

After the base schema, import migration files from `database/migrations/` in
numeric order. `002_catholic_canon.sql` seeds the 73-book Catholic canon and
inactive metadata for the approved public-domain Douay-Rheims source. It does
not import Bible text or activate the translation.

Migration `003_bible_text_import.sql` adds verified package provenance. After applying it, run `php bin/import-bible.php` from the project root to import and activate DRA1899. Migration `004_passage_public_ids.sql` must be applied before using progress APIs; it assigns non-sequential public identifiers to existing and future plan passages.
