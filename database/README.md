# Database setup

`schema.sql` is the initial, empty application schema. It creates structure only;
Bible text and canon seed data are intentionally not included yet.

## IONOS import

1. Create a MySQL database in the IONOS Control Panel under **Hosting > Databases**.
2. Record the generated database host, database name, username, and password.
3. Open the database's phpMyAdmin action from IONOS.
4. Select the database, choose **Import**, upload `schema.sql`, retain UTF-8, and run the import.
5. Confirm that `schema_migrations` contains `001_database_foundation`.

Do not upload a populated `config/local.php` to source control. Prefer IONOS
environment variables when available; otherwise copy `config/config.example.php`
to `config/local.php` on the server and replace every placeholder there.

