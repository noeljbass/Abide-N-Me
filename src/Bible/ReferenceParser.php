<?php

declare(strict_types=1);

namespace FeedMySheep\Bible;

use InvalidArgumentException;

final class ReferenceParser
{
    /** @param array<string,string> $bookAliases normalized name => canonical code */
    public function __construct(private readonly array $bookAliases)
    {
    }

    public function parse(string $input): PassageReference
    {
        $input = trim(preg_replace('/\s+/', ' ', $input));
        if (!preg_match('/^(.+?)\s+(\d+)(?::(\d+))?(?:\s*-\s*(?:(.+?)\s+)?(\d+)(?::(\d+))?)?$/u', $input, $match)) {
            throw new InvalidArgumentException('The Bible reference format is not recognized.');
        }

        $startBook = $this->resolveBook($match[1]);
        $startChapter = (int) $match[2];
        $startVerse = ($match[3] ?? '') !== '' ? (int) $match[3] : null;
        if (($match[4] ?? '') !== '') {
            $endBook = $this->resolveBook($match[4]);
            $endChapter = (int) $match[5];
            $endVerse = ($match[6] ?? '') !== '' ? (int) $match[6] : null;
        } elseif (($match[5] ?? '') !== '') {
            $endBook = $startBook;
            if ($startVerse !== null && ($match[6] ?? '') === '') {
                $endChapter = $startChapter;
                $endVerse = (int) $match[5];
            } else {
                $endChapter = (int) $match[5];
                $endVerse = ($match[6] ?? '') !== '' ? (int) $match[6] : null;
            }
        } else {
            $endBook = $startBook;
            $endChapter = $startChapter;
            $endVerse = $startVerse;
        }

        if ($startVerse === null && $endVerse !== null) {
            throw new InvalidArgumentException('A verse range must include a starting verse.');
        }
        return new PassageReference($startBook, $startChapter, $startVerse, $endBook, $endChapter, $endVerse, $input);
    }

    private function resolveBook(string $name): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/[.\s]+/', ' ', $name)));
        if (!isset($this->bookAliases[$normalized])) {
            throw new InvalidArgumentException(sprintf('Unknown Bible book: %s', trim($name)));
        }
        return $this->bookAliases[$normalized];
    }
}

