<?php

declare(strict_types=1);

namespace FeedMySheep\Bible;

use RuntimeException;
use ZipArchive;

/** Reads a pinned eBible USFM package without extracting archive-controlled paths. */
final class UsfmPackage
{
    public const MAX_ENTRIES = 200;
    public const MAX_UNCOMPRESSED_BYTES = 30_000_000;
    private const ANCILLARY_CODES = ['FRT', 'INT', 'BAK', 'CNC', 'GLO', 'TDX', 'NDX'];
    public function __construct(private readonly string $archive, private readonly array $manifest)
    {
    }

    public function inspect(): array
    {
        if (!is_file($this->archive)) throw new RuntimeException('The configured source package is missing.');
        $expected = strtolower((string)($this->manifest['package']['sha256'] ?? ''));
        $actual = hash_file('sha256', $this->archive);
        if ($expected === '' || !hash_equals($expected, $actual)) throw new RuntimeException('Package SHA-256 does not match the retained source manifest. Import refused.');
        $zip = new ZipArchive();
        if ($zip->open($this->archive) !== true) throw new RuntimeException('The source package is not a readable ZIP archive.');
        try {
            if ($zip->numFiles > self::MAX_ENTRIES) throw new RuntimeException('ZIP entry-count safety limit exceeded.');
            $entries=[]; $books=[]; $total=0;
            for ($i=0; $i<$zip->numFiles; $i++) {
                $stat=$zip->statIndex($i); $name=(string)$stat['name']; $size=(int)$stat['size'];
                if ($name==='' || str_contains($name,"\0") || str_starts_with($name,'/') || preg_match('~(^|/)(\.\.?)(/|$)~',$name) || preg_match('~^[A-Za-z]:[\\\\/]~',$name)) throw new RuntimeException('Unsafe ZIP entry path detected.');
                $total += $size; if ($total > self::MAX_UNCOMPRESSED_BYTES) throw new RuntimeException('ZIP uncompressed-size safety limit exceeded.');
                if (!preg_match('/\.usfm$/i',$name) && !in_array($name,['copr.htm','keys.asc','gentium.css','gentiumplus.css','latin.css'],true)) throw new RuntimeException('Unexpected ZIP entry type: '.basename($name));
                $entries[]=['name'=>$name,'bytes'=>$size];
                if (!preg_match('/\.usfm$/i',$name)) continue;
                $text=$zip->getFromIndex($i); if ($text===false || !mb_check_encoding($text,'UTF-8')) throw new RuntimeException('A USFM entry is unreadable or not UTF-8.');
                $providerCode=$this->extractId($text,$name);
                $code=$this->canonicalCode($providerCode);
                $canonical=$this->manifest['book_codes'] ?? BookCatalog::CATHOLIC_CODES;
                if (!in_array($code,$canonical,true)) {
                    $excluded=$this->manifest['excluded_book_codes'] ?? [];
                    if (in_array($providerCode,$excluded,true)) continue;
                    $hasChapters=(bool)preg_match('/^\\\\c\s+\d+/m',$text);
                    if (in_array($providerCode,self::ANCILLARY_CODES,true) && !$hasChapters) continue;
                    if ($hasChapters) throw new RuntimeException("Unexpected chapter-bearing USFM identifier {$providerCode} in {$name}.");
                    throw new RuntimeException("Unexpected non-book USFM identifier {$providerCode} in {$name}.");
                }
                $book=$this->parse($text,$name,$code,$providerCode); if(isset($books[$book['code']])) throw new RuntimeException('Conflicting USFM book code: '.$book['code']);
                $books[$book['code']]=$book;
            }
        } finally { $zip->close(); }
        $this->validate($books);
        ksort($books);
        return ['source_identifier'=>$this->manifest['source_identifier'],'sha256'=>$actual,'entries'=>$entries,'markers'=>['declared_usfm_version'=>null],'books'=>$books,'summary'=>['entries'=>count($entries),'books'=>count($books),'chapters'=>array_sum(array_column($books,'chapter_count')),'verses'=>array_sum(array_column($books,'verse_count')),'uncompressed_bytes'=>$total], 'numbering'=>['provider_mapping'=>'USFM book/chapter/verse identifiers retained in translation_books.numbering_metadata.']];
    }

    private function parse(string $text,string $filename,string $code,string $providerCode): array
    {
        $chapters=[]; $current=null;
        $parts=preg_split('/(?=^\\\\(?:c|v)\s+)/m',$text);
        foreach($parts as $part){
            if(preg_match('/^\\\\c\s+(\d+)/',$part,$x)){ $current=(int)$x[1]; $chapters[$current]??=[]; continue; }
            if(!preg_match('/^\\\\v\s+(\d+)([a-z]?)\s*(.*)$/s',$part,$x) || $current===null) continue;
            // Notes are metadata, not part of the verse text. Remove their entire
            // contents before stripping ordinary character/paragraph markers.
            $body=preg_replace('/\\\\\+?(f|fe|x)\b.*?\\\\\+?\1\*/us', '', $x[3]);
            // Word-study fields may use either \w or the nested-marker form \+w.
            // Keep the displayed word while discarding Strong's numbers and other
            // attributes following the pipe.
            $body=preg_replace('/\\\\\+?w\s+([^|\\\\]+)(?:\|[^\\\\]*)?\\\\\+?w\*/u', '$1', $body);
            $body=preg_replace('/\\\\(?:p|q\d*|m|mi|nb|b)\b\s*/u',' ',$body);
            $body=preg_replace('/\\\\[A-Za-z0-9]+\*?\s*/u', ' ', $body);
            $body=trim(preg_replace('/\s+/u',' ',$body));
            $body=preg_replace('/\s+([,.;:!?])/u', '$1', $body);
            $body=preg_replace('/([,.;:!?])(?=\p{L})/u', '$1 ', $body);
            if($body==='') {
                if (($this->manifest['empty_verse_policy'] ?? 'reject') === 'omit') continue;
                throw new RuntimeException("Empty verse {$code} {$current}:{$x[1]}.");
            }
            $key=(int)$x[1].($x[2]??''); if(isset($chapters[$current][$key])) throw new RuntimeException("Duplicate verse {$code} {$current}:{$key}.");
            $chapters[$current][$key]=['verse'=>(int)$x[1],'suffix'=>$x[2]??'','text'=>$body];
        }
        if(!$chapters) throw new RuntimeException("No chapters found in {$filename}.");
        $expected=range(1,max(array_keys($chapters))); if(array_keys($chapters)!==$expected) throw new RuntimeException("Non-contiguous chapters in {$code}.");
        return ['code'=>$code,'provider_code'=>$providerCode,'filename'=>$filename,'chapter_count'=>count($chapters),'verse_count'=>array_sum(array_map('count',$chapters)),'chapters'=>$chapters];
    }

    private function extractId(string $text,string $filename): string
    {
        if(!preg_match('/^\\\\id\s+([0-9A-Z]{3})\b/m',$text,$match)) throw new RuntimeException("Missing USFM id in {$filename}.");
        return $match[1];
    }

    private function canonicalCode(string $providerCode): string
    {
        return (string)($this->manifest['book_code_map'][$providerCode] ?? $providerCode);
    }

    private function validate(array $books): void
    {
        $canonical=$this->manifest['book_codes'] ?? BookCatalog::CATHOLIC_CODES;
        $missing=array_values(array_diff($canonical,array_keys($books))); $extra=array_values(array_diff(array_keys($books),$canonical));
        if(count($books)!==count($canonical) || $missing || $extra) throw new RuntimeException('Canon validation failed. Missing: '.implode(',',$missing).'; unexpected: '.implode(',',$extra).'.');
        if(in_array('PSA',$canonical,true) && $books['PSA']['chapter_count']!==150) throw new RuntimeException('Unexpected Psalm chapter count.');
    }
}
