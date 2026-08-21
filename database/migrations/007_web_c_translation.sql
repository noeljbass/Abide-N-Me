START TRANSACTION;
INSERT INTO translations (provider_id,canon_id,code,name,language_code,copyright_notice,license_url,offline_allowed,is_active)
SELECT p.id,c.id,'WEB-C','World English Bible, Catholic Edition','en','Public domain. Source package: eBible.org eng-web-c.','https://ebible.org/Scriptures/details.php?id=eng-web-c',TRUE,FALSE
FROM bible_providers p JOIN canons c ON c.code='catholic-73' WHERE p.code='local'
ON DUPLICATE KEY UPDATE name=VALUES(name),copyright_notice=VALUES(copyright_notice),license_url=VALUES(license_url),offline_allowed=TRUE;
INSERT INTO schema_migrations (version) VALUES ('007_web_c_translation') ON DUPLICATE KEY UPDATE version=VALUES(version);
COMMIT;
