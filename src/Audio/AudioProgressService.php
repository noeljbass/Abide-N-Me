<?php

declare(strict_types=1);

namespace FeedMySheep\Audio;

use InvalidArgumentException;
use PDO;

final class AudioProgressService
{
    public function __construct(private readonly PDO $db) {}

    public function get(int $userId, string $passagePublicId): ?array
    {
        $passage = $this->authorizedPassage($userId, $passagePublicId);
        $query = $this->db->prepare('SELECT av.provider_fileset_id audio_version,b.code book,ap.chapter,ap.verse,ap.audio_position_seconds,ap.listened_seconds,ap.updated_at FROM audio_progress ap JOIN audio_versions av ON av.id=ap.audio_version_id JOIN books b ON b.id=ap.book_id WHERE ap.user_id=? AND ap.passage_id=?');
        $query->execute([$userId, $passage['id']]);
        $row = $query->fetch();
        if (!$row) return null;
        $row['chapter'] = (int) $row['chapter'];
        $row['verse'] = $row['verse'] === null ? null : (int) $row['verse'];
        $row['audio_position_seconds'] = (float) $row['audio_position_seconds'];
        $row['listened_seconds'] = (int) $row['listened_seconds'];
        return $row;
    }

    public function save(int $userId, string $passagePublicId, array $input): array
    {
        $passage = $this->authorizedPassage($userId, $passagePublicId);
        $position = filter_var($input['audio_position_seconds'] ?? null, FILTER_VALIDATE_FLOAT);
        $chapter = filter_var($input['chapter'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $bookCode = strtoupper(trim((string) ($input['book'] ?? '')));
        $versionCode = trim((string) ($input['audio_version'] ?? ''));
        $verse = isset($input['verse']) ? filter_var($input['verse'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
        if ($position === false || $position < 0 || $position > 86400 || $chapter === false || !preg_match('/^[0-9A-Z]{3}$/', $bookCode) || $versionCode === '') {
            throw new InvalidArgumentException('The playback position is invalid.');
        }
        if (isset($input['verse']) && $verse === false) throw new InvalidArgumentException('The playback verse is invalid.');
        $lookup = $this->db->prepare('SELECT av.id audio_version_id,b.id book_id FROM audio_versions av JOIN audio_providers provider ON provider.id=av.provider_id JOIN provider_book_mappings pbm ON pbm.provider_kind=\'audio\' AND pbm.provider_id=provider.id JOIN books b ON b.id=pbm.book_id WHERE av.provider_fileset_id=? AND av.is_active=TRUE AND provider.is_active=TRUE AND b.code=?');
        $lookup->execute([$versionCode, $bookCode]); $mapping = $lookup->fetch();
        if (!$mapping) throw new InvalidArgumentException('That audio version is unavailable for this book.');
        $this->assertInsidePassage($passage, (int) $mapping['book_id'], (int) $chapter, $verse === null ? null : (int) $verse);
        $listenedDelta = filter_var($input['listened_seconds_delta'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 120]]);
        if ($listenedDelta === false) $listenedDelta = 0;
        $statement = $this->db->prepare('INSERT INTO audio_progress(user_id,passage_id,audio_version_id,book_id,chapter,verse,audio_position_seconds,listened_seconds) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE audio_version_id=VALUES(audio_version_id),book_id=VALUES(book_id),chapter=VALUES(chapter),verse=VALUES(verse),audio_position_seconds=VALUES(audio_position_seconds),listened_seconds=listened_seconds+VALUES(listened_seconds)');
        $statement->execute([$userId,$passage['id'],$mapping['audio_version_id'],$mapping['book_id'],$chapter,$verse,$position,$listenedDelta]);
        return $this->get($userId, $passagePublicId);
    }

    private function authorizedPassage(int $userId, string $publicId): array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $publicId)) throw new InvalidArgumentException('Passage not found.');
        $query = $this->db->prepare("SELECT pp.*,rp.canon_id FROM plan_passages pp JOIN plan_days pd ON pd.id=pp.plan_day_id JOIN reading_plans rp ON rp.id=pd.plan_id JOIN group_plans gp ON gp.plan_id=rp.id JOIN group_members gm ON gm.group_id=gp.group_id WHERE pp.public_id=? AND gm.user_id=? AND gm.status='active' LIMIT 1");
        $query->execute([$publicId,$userId]); $passage=$query->fetch();
        if (!$passage) throw new InvalidArgumentException('Passage not found.');
        return $passage;
    }

    private function assertInsidePassage(array $passage, int $bookId, int $chapter, ?int $verse): void
    {
        $position=$this->db->prepare('SELECT book_id,position FROM canon_books WHERE canon_id=? AND book_id IN (?,?,?)');
        $position->execute([$passage['canon_id'],$passage['start_book_id'],$passage['end_book_id'],$bookId]);
        $map=[];foreach($position->fetchAll() as $row)$map[(int)$row['book_id']]=(int)$row['position'];
        if(!isset($map[$bookId])||[$map[$bookId],$chapter,$verse??0]<[$map[(int)$passage['start_book_id']],(int)$passage['start_chapter'],(int)($passage['start_verse']??0)]||[$map[$bookId],$chapter,$verse??65535]>[$map[(int)$passage['end_book_id']],(int)$passage['end_chapter'],(int)($passage['end_verse']??65535)]) throw new InvalidArgumentException('The playback location is outside this assignment.');
    }
}
