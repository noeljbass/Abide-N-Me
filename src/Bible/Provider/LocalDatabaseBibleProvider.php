<?php

declare(strict_types=1);

namespace FeedMySheep\Bible\Provider;

use FeedMySheep\Bible\PassageReference;
use InvalidArgumentException;
use PDO;

final class LocalDatabaseBibleProvider implements BibleProviderInterface
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function getTranslations(): array
    {
        return $this->database->query("SELECT code, name, language_code, copyright_notice, license_url, offline_allowed FROM translations WHERE is_active = TRUE ORDER BY name")->fetchAll();
    }

    public function getBooks(string $translationCode): array
    {
        $query = $this->database->prepare('SELECT b.code, COALESCE(tb.provider_name,b.name) AS name, tb.chapter_count FROM translation_books tb JOIN translations t ON t.id=tb.translation_id JOIN books b ON b.id=tb.book_id WHERE t.code=:translation ORDER BY (SELECT cb.position FROM canon_books cb WHERE cb.canon_id=t.canon_id AND cb.book_id=b.id)');
        $query->execute(['translation' => $translationCode]);
        return $query->fetchAll();
    }

    public function getChapter(string $translationCode, string $bookCode, int $chapter): array
    {
        if ($chapter < 1) throw new InvalidArgumentException('Chapter must be positive.');
        $query = $this->database->prepare('SELECT v.verse, v.verse_suffix, v.text FROM bible_verses v JOIN translations t ON t.id=v.translation_id JOIN books b ON b.id=v.book_id WHERE t.code=:translation AND b.code=:book AND v.chapter=:chapter ORDER BY v.verse,v.verse_suffix');
        $query->execute(['translation' => $translationCode, 'book' => $bookCode, 'chapter' => $chapter]);
        return $query->fetchAll();
    }

    public function getPassage(string $translationCode, PassageReference $reference): array
    {
        if ($reference->startBook !== $reference->endBook || $reference->startChapter !== $reference->endChapter) {
            throw new InvalidArgumentException('Cross-chapter passage expansion belongs to the passage orchestration layer.');
        }
        $verses = $this->getChapter($translationCode, $reference->startBook, $reference->startChapter);
        return array_values(array_filter($verses, static fn(array $verse): bool =>
            ($reference->startVerse === null || (int) $verse['verse'] >= $reference->startVerse)
            && ($reference->endVerse === null || (int) $verse['verse'] <= $reference->endVerse)
        ));
    }

    public function search(string $translationCode, string $query, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $statement = $this->database->prepare("SELECT b.code AS book, v.chapter, v.verse, v.text FROM bible_verses v JOIN translations t ON t.id=v.translation_id JOIN books b ON b.id=v.book_id WHERE t.code=:translation AND v.text LIKE :query ORDER BY b.id,v.chapter,v.verse LIMIT {$limit}");
        $statement->execute(['translation' => $translationCode, 'query' => '%' . $query . '%']);
        return $statement->fetchAll();
    }
}
