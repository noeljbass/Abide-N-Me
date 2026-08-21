<?php

declare(strict_types=1);

namespace FeedMySheep\Bible;

use InvalidArgumentException;
use PDO;

final class BibleReaderService
{
 public function __construct(private readonly PDO $db) {}
 public function chapter(string $translation,string $book,int $chapter): array {
  if($chapter<1) throw new InvalidArgumentException('Chapter must be at least 1.');
  $meta=$this->meta($translation,$book); if($chapter>$meta['chapter_count']) throw new InvalidArgumentException('That chapter does not exist in this translation.');
  $q=$this->db->prepare('SELECT verse,verse_suffix,text FROM bible_verses WHERE translation_id=? AND book_id=? AND chapter=? ORDER BY verse,verse_suffix'); $q->execute([$meta['translation_id'],$meta['book_id'],$chapter]);
  $verses=array_map(fn($v)=>['verse'=>(int)$v['verse'],'suffix'=>$v['verse_suffix'],'text'=>$v['text']],$q->fetchAll());
  if(!$verses) throw new InvalidArgumentException('Scripture text is not available for that chapter.');
  return ['translation'=>$meta['translation_code'],'book'=>['code'=>$book,'name'=>$meta['book_name'],'chapter_count'=>$meta['chapter_count']],'chapter'=>$chapter,'verses'=>$verses];
 }
 public function passage(string $translation,PassageReference $r): array {
  $start=$this->order($translation,$r->startBook,$r->startChapter,$r->startVerse??0); $end=$this->order($translation,$r->endBook,$r->endChapter,$r->endVerse??65535); if($start>$end) throw new InvalidArgumentException('The passage end must follow its start.');
  $q=$this->db->prepare('SELECT b.code AS book,b.name,v.chapter,v.verse,v.verse_suffix,v.text FROM bible_verses v JOIN translations t ON t.id=v.translation_id JOIN books b ON b.id=v.book_id JOIN canon_books cb ON cb.canon_id=t.canon_id AND cb.book_id=b.id WHERE t.code=:t AND ((cb.position>:sp OR (cb.position=:sp AND (v.chapter>:sc OR (v.chapter=:sc AND v.verse>=:sv)))) AND (cb.position<:ep OR (cb.position=:ep AND (v.chapter<:ec OR (v.chapter=:ec AND v.verse<=:ev))))) ORDER BY cb.position,v.chapter,v.verse,v.verse_suffix');
  $q->execute(['t'=>$translation,'sp'=>$start[0],'sc'=>$r->startChapter,'sv'=>$r->startVerse??0,'ep'=>$end[0],'ec'=>$r->endChapter,'ev'=>$r->endVerse??65535]); $verses=$q->fetchAll(); if(!$verses) throw new InvalidArgumentException('No Scripture text was found for that passage.');
  foreach($verses as &$v){$v['chapter']=(int)$v['chapter'];$v['verse']=(int)$v['verse'];} return ['translation'=>$translation,'reference'=>$r->toArray(),'verses'=>$verses];
 }
 private function meta(string $translation,string $book): array {$q=$this->db->prepare('SELECT t.id translation_id,t.code translation_code,b.id book_id,b.name book_name,tb.chapter_count FROM translation_books tb JOIN translations t ON t.id=tb.translation_id JOIN books b ON b.id=tb.book_id WHERE t.code=? AND t.is_active=TRUE AND b.code=?');$q->execute([$translation,$book]);$r=$q->fetch();if(!$r)throw new InvalidArgumentException('Translation or book not found.');$r['chapter_count']=(int)$r['chapter_count'];return $r;}
 private function order(string $translation,string $book,int $chapter,int $verse): array {$m=$this->meta($translation,$book);if($chapter>$m['chapter_count'])throw new InvalidArgumentException('A referenced chapter does not exist.');$q=$this->db->prepare('SELECT cb.position FROM canon_books cb JOIN translations t ON t.canon_id=cb.canon_id WHERE t.code=? AND cb.book_id=?');$q->execute([$translation,$m['book_id']]);return [(int)$q->fetchColumn(),$chapter,$verse];}
}
