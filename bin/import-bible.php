#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app=require dirname(__DIR__).'/src/bootstrap.php';
use FeedMySheep\Bible\UsfmPackage;
use FeedMySheep\Database;
$manifestPath=dirname(__DIR__).'/database/bible-sources.json';
$manifest=json_decode((string)file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
if(!isset($manifest['engDRA'])) { fwrite(STDERR,"Required engDRA manifest record not found.\n"); exit(1); }
try {
 $report=(new UsfmPackage(dirname(__DIR__).'/storage/imports/'.$manifest['engDRA']['package']['filename'],$manifest['engDRA']))->inspect();
 $compact=$report; foreach($compact['books'] as &$b) unset($b['chapters']); unset($b);
 if(in_array('--validate-only',$argv,true)){ echo json_encode($compact,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n"; exit; }
 $pdo=(new Database($app['config']))->connection(); $pdo->beginTransaction();
 try {
  $translation=$pdo->query("SELECT id FROM translations WHERE code='DRA1899' FOR UPDATE")->fetchColumn(); if(!$translation) throw new RuntimeException('Run schema.sql and migration 002 before importing.');
  $pdo->prepare('UPDATE translations SET is_active=FALSE WHERE id=?')->execute([$translation]);
  $bookIds=$pdo->query('SELECT code,id FROM books')->fetchAll(PDO::FETCH_KEY_PAIR);
  $pdo->prepare('DELETE FROM translation_books WHERE translation_id=?')->execute([$translation]);
  $tb=$pdo->prepare('INSERT INTO translation_books (translation_id,book_id,provider_book_id,provider_name,chapter_count,numbering_metadata) VALUES (?,?,?,?,?,?)');
  $verse=$pdo->prepare('INSERT INTO bible_verses (translation_id,book_id,chapter,verse,verse_suffix,text) VALUES (?,?,?,?,?,?)');
  foreach($report['books'] as $code=>$book){ if(!isset($bookIds[$code])) throw new RuntimeException("Canonical database mapping missing for {$code}."); $metadata=json_encode(['source_file'=>$book['filename'],'provider_book_id'=>$code,'numbering'=>$report['numbering']],JSON_THROW_ON_ERROR); $tb->execute([$translation,$bookIds[$code],$code,$code,$book['chapter_count'],$metadata]); foreach($book['chapters'] as $chapter=>$verses) foreach($verses as $v) $verse->execute([$translation,$bookIds[$code],$chapter,$v['verse'],$v['suffix'],$v['text']]); }
  $validation=$compact; $insert=$pdo->prepare('INSERT INTO bible_imports (translation_id,source_identifier,package_filename,package_sha256,source_url,book_count,chapter_count,verse_count,validation_report) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE imported_at=CURRENT_TIMESTAMP,book_count=VALUES(book_count),chapter_count=VALUES(chapter_count),verse_count=VALUES(verse_count),validation_report=VALUES(validation_report)');
  $insert->execute([$translation,'engDRA',$manifest['engDRA']['package']['filename'],$report['sha256'],$manifest['engDRA']['package']['url'],$report['summary']['books'],$report['summary']['chapters'],$report['summary']['verses'],json_encode($validation,JSON_THROW_ON_ERROR)]);
  $pdo->prepare('UPDATE translations SET is_active=TRUE WHERE id=?')->execute([$translation]); $pdo->commit();
 } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
 echo "Imported and activated DRA1899: {$report['summary']['books']} books, {$report['summary']['chapters']} chapters, {$report['summary']['verses']} verses.\n";
} catch(Throwable $e){ fwrite(STDERR,'Import failed: '.$e->getMessage()."\n"); exit(1); }
