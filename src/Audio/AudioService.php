<?php

declare(strict_types=1);

namespace FeedMySheep\Audio;

use FeedMySheep\Bible\Provider\AudioProviderInterface;
use InvalidArgumentException;
use PDO;

final class AudioService
{
    public function __construct(private readonly PDO $db, private readonly ?AudioProviderInterface $provider = null) {}

    public function versions(): array
    {
        $query = $this->db->query("SELECT av.provider_fileset_id AS code,av.name,av.language_code,av.has_verse_timing,t.code translation_code,ap.name provider_name FROM audio_versions av JOIN audio_providers ap ON ap.id=av.provider_id LEFT JOIN translations t ON t.id=av.translation_id WHERE av.is_active=TRUE AND ap.is_active=TRUE ORDER BY av.name");
        return array_map(static fn(array $row): array => [
            'code' => $row['code'], 'name' => $row['name'], 'language_code' => $row['language_code'],
            'has_verse_timing' => (bool) $row['has_verse_timing'], 'translation_code' => $row['translation_code'],
            'provider_name' => $row['provider_name'],
        ], $query->fetchAll());
    }

    public function chapter(string $version, string $book, int $chapter): array
    {
        if (!$this->provider) throw new InvalidArgumentException('Bible audio is not configured yet.');
        if ($chapter < 1 || !preg_match('/^[0-9A-Z]{3}$/', $book)) throw new InvalidArgumentException('Choose a valid book and chapter.');
        $allowed = $this->db->prepare("SELECT 1 FROM audio_versions av JOIN audio_providers ap ON ap.id=av.provider_id JOIN provider_book_mappings pbm ON pbm.provider_kind='audio' AND pbm.provider_id=ap.id JOIN books b ON b.id=pbm.book_id WHERE av.provider_fileset_id=? AND av.is_active=TRUE AND ap.is_active=TRUE AND b.code=?");
        $allowed->execute([$version, $book]);
        if (!$allowed->fetchColumn()) throw new InvalidArgumentException('Audio is unavailable for that book or version.');
        return AudioMetadata::chapter($this->provider->getAudio($version, $book, $chapter));
    }
}
