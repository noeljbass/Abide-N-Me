START TRANSACTION;
INSERT INTO translations (provider_id,canon_id,code,name,language_code,copyright_notice,license_url,offline_allowed,is_active)
SELECT p.id,c.id,'BSB','Berean Standard Bible','en','Public domain. Source package: eBible.org engbsb.','https://ebible.org/Scriptures/details.php?id=engbsb',TRUE,FALSE
FROM bible_providers p JOIN canons c ON c.code='protestant-66' WHERE p.code='local'
ON DUPLICATE KEY UPDATE name=VALUES(name),copyright_notice=VALUES(copyright_notice),license_url=VALUES(license_url),offline_allowed=TRUE;
INSERT INTO schema_migrations (version) VALUES ('009_bsb_translation') ON DUPLICATE KEY UPDATE version=VALUES(version);
COMMIT;
