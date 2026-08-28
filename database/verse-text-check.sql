-- Read-only checks. Nothing here changes data.
--
-- Run this in phpMyAdmin to see what state the imported Scripture is actually
-- in. Each query stands alone; run them one at a time if the tool only shows
-- the last result.

-- 1. Which translations still contain raw USFM markup?
--    Anything above zero means that translation was imported before the parser
--    learned to strip character markers, and needs a rebuild.
SELECT t.code,
       t.name,
       COUNT(*) AS verses_with_markup
FROM bible_verses bv
JOIN translations t ON t.id = bv.translation_id
WHERE INSTR(bv.text, '\\') > 0
GROUP BY t.code, t.name
ORDER BY verses_with_markup DESC;

-- 2. Did the Berean heading correction (011) actually apply?
--    Expected after 011:  "and Abram (that is, Abraham)."
--    Still damaged:       "... Abraham). The Descendants of Abraham (Genesis 25:12-18)"
SELECT t.code, bv.chapter, bv.verse, bv.text
FROM bible_verses bv
JOIN translations t ON t.id = bv.translation_id
JOIN books b ON b.id = bv.book_id
WHERE t.code = 'BSB' AND b.code = '1CH' AND bv.chapter = 1 AND bv.verse = 27;

-- 3. Did the King James rebuild (013) actually apply?
--    Expected after 013: no backslashes, ending "...which is by interpretation, A stone."
SELECT t.code, bv.chapter, bv.verse, bv.text
FROM bible_verses bv
JOIN translations t ON t.id = bv.translation_id
JOIN books b ON b.id = bv.book_id
WHERE t.code = 'KJV' AND b.code = 'JHN' AND bv.chapter = 1 AND bv.verse = 42;

-- 4. How many verses does each translation hold?
--    Expected: DRA1899 35811, KJV 31102, BSB 31086, WEB-C 35384.
SELECT t.code, COUNT(*) AS verses
FROM bible_verses bv
JOIN translations t ON t.id = bv.translation_id
GROUP BY t.code
ORDER BY t.code;
