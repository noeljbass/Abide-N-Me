<?php

declare(strict_types=1);

namespace FeedMySheep\Bible;

use InvalidArgumentException;

final readonly class PassageReference
{
    public function __construct(
        public string $startBook,
        public int $startChapter,
        public ?int $startVerse,
        public string $endBook,
        public int $endChapter,
        public ?int $endVerse,
        public string $display
    ) {
        if ($startChapter < 1 || $endChapter < 1 || ($startVerse !== null && $startVerse < 1) || ($endVerse !== null && $endVerse < 1)) {
            throw new InvalidArgumentException('Chapter and verse numbers must be positive.');
        }
    }

    public function toArray(): array
    {
        return [
            'start' => ['book' => $this->startBook, 'chapter' => $this->startChapter, 'verse' => $this->startVerse],
            'end' => ['book' => $this->endBook, 'chapter' => $this->endChapter, 'verse' => $this->endVerse],
            'display' => $this->display,
        ];
    }
}

