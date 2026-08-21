<?php

declare(strict_types=1);

namespace FeedMySheep\Bible\Provider;

use FeedMySheep\Bible\PassageReference;

interface BibleProviderInterface
{
    public function getTranslations(): array;
    public function getBooks(string $translationCode): array;
    public function getChapter(string $translationCode, string $bookCode, int $chapter): array;
    public function getPassage(string $translationCode, PassageReference $reference): array;
    public function search(string $translationCode, string $query, int $limit = 50): array;
}

