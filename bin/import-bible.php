#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php';

use FeedMySheep\Bible\UsfmPackage;
use FeedMySheep\Database;

$source = 'engDRA';
foreach ($argv as $argument) if (str_starts_with($argument, '--source=')) $source = substr($argument, 9);
$manifest = json_decode((string) file_get_contents(dirname(__DIR__) . '/database/bible-sources.json'), true, 512, JSON_THROW_ON_ERROR);
if (!isset($manifest[$source])) { fwrite(STDERR, "Unknown Bible source: {$source}.\n"); exit(1); }
$record = $manifest[$source];

try {
    $report = (new UsfmPackage(dirname(__DIR__) . '/storage/imports/' . $record['package']['filename'], $record))->inspect();
    $compact = $report;
    foreach ($compact['books'] as &$book) unset($book['chapters']);
    unset($book);
    if (in_array('--validate-only', $argv, true)) { echo json_encode($compact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"; exit; }

    $pdo = (new Database($app['config']))->connection();
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT id FROM translations WHERE code=? FOR UPDATE');
        $query->execute([$record['translation_code']]);
        $translation = $query->fetchColumn();
        if (!$translation) throw new RuntimeException('Run the schema and translation migration before importing.');
        $pdo->prepare('UPDATE translations SET is_active=FALSE WHERE id=?')->execute([$translation]);
        $bookIds = $pdo->query('SELECT code,id FROM books')->fetchAll(PDO::FETCH_KEY_PAIR);
        $pdo->prepare('DELETE FROM translation_books WHERE translation_id=?')->execute([$translation]);
        $insertBook = $pdo->prepare('INSERT INTO translation_books (translation_id,book_id,provider_book_id,provider_name,chapter_count,numbering_metadata) VALUES (?,?,?,?,?,?)');
        $insertVerse = $pdo->prepare('INSERT INTO bible_verses (translation_id,book_id,chapter,verse,verse_suffix,text) VALUES (?,?,?,?,?,?)');
        foreach ($report['books'] as $code => $book) {
            if (!isset($bookIds[$code])) throw new RuntimeException("Canonical database mapping missing for {$code}.");
            $metadata = json_encode(['source_file'=>$book['filename'],'provider_book_id'=>$code,'numbering'=>$report['numbering']], JSON_THROW_ON_ERROR);
            $insertBook->execute([$translation,$bookIds[$code],$code,$code,$book['chapter_count'],$metadata]);
            foreach ($book['chapters'] as $chapter => $verses) foreach ($verses as $verse) $insertVerse->execute([$translation,$bookIds[$code],$chapter,$verse['verse'],$verse['suffix'],$verse['text']]);
        }
        $insert = $pdo->prepare('INSERT INTO bible_imports (translation_id,source_identifier,package_filename,package_sha256,source_url,book_count,chapter_count,verse_count,validation_report) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE imported_at=CURRENT_TIMESTAMP,book_count=VALUES(book_count),chapter_count=VALUES(chapter_count),verse_count=VALUES(verse_count),validation_report=VALUES(validation_report)');
        $insert->execute([$translation,$source,$record['package']['filename'],$report['sha256'],$record['package']['url'],$report['summary']['books'],$report['summary']['chapters'],$report['summary']['verses'],json_encode($compact,JSON_THROW_ON_ERROR)]);
        $pdo->prepare('UPDATE translations SET is_active=TRUE WHERE id=?')->execute([$translation]);
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
    echo "Imported and activated {$record['translation_code']}: {$report['summary']['books']} books, {$report['summary']['chapters']} chapters, {$report['summary']['verses']} verses.\n";
} catch (Throwable $error) { fwrite(STDERR, 'Import failed: ' . $error->getMessage() . "\n"); exit(1); }
