<?php

declare(strict_types=1);

namespace FeedMySheep\Bible;

use PDO;

final class BookCatalog
{
    public const CATHOLIC_CODES = ['GEN','EXO','LEV','NUM','DEU','JOS','JDG','RUT','1SA','2SA','1KI','2KI','1CH','2CH','EZR','NEH','TOB','JDT','EST','1MA','2MA','JOB','PSA','PRO','ECC','SNG','WIS','SIR','ISA','JER','LAM','BAR','EZK','DAN','HOS','JOL','AMO','OBA','JON','MIC','NAM','HAB','ZEP','HAG','ZEC','MAL','MAT','MRK','LUK','JHN','ACT','ROM','1CO','2CO','GAL','EPH','PHP','COL','1TH','2TH','1TI','2TI','TIT','PHM','HEB','JAS','1PE','2PE','1JN','2JN','3JN','JUD','REV'];

    public function __construct(private readonly PDO $database)
    {
    }

    public function books(string $canonCode = 'catholic-73'): array
    {
        $query = $this->database->prepare('SELECT b.code, b.name, b.testament, cb.position, cb.chapter_count FROM canon_books cb JOIN canons c ON c.id = cb.canon_id JOIN books b ON b.id = cb.book_id WHERE c.code = :canon ORDER BY cb.position');
        $query->execute(['canon' => $canonCode]);
        return array_map(static function (array $book): array {
            $book['position'] = (int) $book['position'];
            $book['chapter_count'] = (int) $book['chapter_count'];
            return $book;
        }, $query->fetchAll());
    }

    public function aliases(): array
    {
        $query = $this->database->query('SELECT LOWER(bn.name) AS alias, b.code FROM book_names bn JOIN books b ON b.id = bn.book_id');
        $aliases = [];
        foreach ($query->fetchAll() as $row) $aliases[$row['alias']] = $row['code'];
        return $aliases;
    }
}

