<?php

declare(strict_types=1);

namespace FeedMySheep;

use InvalidArgumentException;
use PDO;

final class ProgressService
{
    public function __construct(private readonly PDO $db) {}

    public function today(int $userId): array
    {
        $plans = (new ReadingPlanService($this->db))->today($userId);
        $memberQuery = $this->db->prepare(
            "SELECT u.name,gm.user_id,COUNT(pp.id) passage_count,
             COALESCE(SUM(COALESCE(upp.progress_percent,0)),0) progress_sum
             FROM groups g
             JOIN group_members gm ON gm.group_id=g.id AND gm.status='active'
             JOIN users u ON u.id=gm.user_id AND u.status='active'
             JOIN group_plans gp ON gp.group_id=g.id
             JOIN plan_days pd ON pd.plan_id=gp.plan_id
             JOIN plan_passages pp ON pp.plan_day_id=pd.id
             LEFT JOIN user_passage_progress upp ON upp.passage_id=pp.id AND upp.user_id=gm.user_id
             WHERE g.public_id=? AND gp.plan_id=? AND pd.scheduled_date=?
             GROUP BY gm.user_id,u.name ORDER BY FIELD(gm.role,'owner','admin','member'),u.name"
        );
        foreach ($plans as &$assignment) {
            $planId = $this->internalPlanId($assignment['plan']['public_id'], $userId);
            $memberQuery->execute([$assignment['plan']['group_id'], $planId, $assignment['day']['scheduled_date']]);
            $members = [];
            foreach ($memberQuery->fetchAll() as $member) {
                $percent = (int) round((float) $member['progress_sum'] / max(1, (int) $member['passage_count']));
                $members[] = ['name' => $member['name'], 'progress_percent' => $percent, 'completed' => $percent === 100];
            }
            $assignment['members'] = $members;
            foreach ($assignment['day']['passages'] as &$passage) {
                $progress = $this->progressRow($userId, $passage['id']);
                $passage['progress_percent'] = $progress ? (float) $progress['progress_percent'] : 0.0;
                $passage['completed'] = $progress && $progress['completed_at'] !== null;
                $passage['resume'] = $progress && $progress['last_book'] ? [
                    'book' => $progress['last_book'],
                    'chapter' => (int) $progress['last_chapter'],
                    'verse' => $progress['last_verse'] === null ? null : (int) $progress['last_verse'],
                ] : null;
            }
        }
        return $plans;
    }

    public function update(int $userId, string $passagePublicId, array $input): array
    {
        $passage = $this->authorizedPassage($userId, $passagePublicId);
        $complete = ($input['complete'] ?? false) === true;
        if ($complete) {
            $lastBookId = (int) $passage['end_book_id'];
            $chapter = (int) $passage['end_chapter'];
            $verse = $passage['end_verse'] === null ? $this->lastVerse((int) $passage['translation_id'], $lastBookId, $chapter) : (int) $passage['end_verse'];
            $percent = 100.0;
        } else {
            $bookCode = strtoupper(trim((string) ($input['book'] ?? '')));
            $chapter = Validator::positiveInteger($input['chapter'] ?? null);
            $verse = Validator::positiveInteger($input['verse'] ?? null);
            if (!$chapter || !$verse) throw new InvalidArgumentException('A valid current verse is required.');
            $book = $this->db->prepare('SELECT id FROM books WHERE code=?');
            $book->execute([$bookCode]); $lastBookId = (int) $book->fetchColumn();
            if (!$lastBookId) throw new InvalidArgumentException('The current book is not recognized.');
            [$position, $total] = $this->passagePosition($passage, $lastBookId, $chapter, $verse);
            $percent = min(99.0, max(0.0, round(($position / max(1, $total)) * 100, 2)));
        }
        $statement = $this->db->prepare(
            'INSERT INTO user_passage_progress(user_id,passage_id,progress_percent,last_book_id,last_chapter,last_verse,completed_at)
             VALUES(?,?,?,?,?,?,IF(?=100,UTC_TIMESTAMP(),NULL))
             ON DUPLICATE KEY UPDATE progress_percent=GREATEST(progress_percent,VALUES(progress_percent)),
             last_book_id=IF(VALUES(progress_percent)>=progress_percent,VALUES(last_book_id),last_book_id),
             last_chapter=IF(VALUES(progress_percent)>=progress_percent,VALUES(last_chapter),last_chapter),
             last_verse=IF(VALUES(progress_percent)>=progress_percent,VALUES(last_verse),last_verse),
             completed_at=IF(VALUES(progress_percent)=100,COALESCE(completed_at,UTC_TIMESTAMP()),completed_at)'
        );
        $statement->execute([$userId, $passage['id'], $percent, $lastBookId, $chapter, $verse, $percent]);
        return ['passage_id' => $passagePublicId, 'progress_percent' => $percent, 'completed' => $percent === 100.0];
    }

    private function authorizedPassage(int $userId, string $publicId): array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $publicId)) throw new InvalidArgumentException('Passage not found.');
        $query = $this->db->prepare(
            "SELECT pp.*,rp.default_translation_id translation_id
             FROM plan_passages pp JOIN plan_days pd ON pd.id=pp.plan_day_id
             JOIN reading_plans rp ON rp.id=pd.plan_id JOIN group_plans gp ON gp.plan_id=rp.id
             JOIN group_members gm ON gm.group_id=gp.group_id
             WHERE pp.public_id=? AND gm.user_id=? AND gm.status='active' LIMIT 1"
        );
        $query->execute([$publicId, $userId]); $passage = $query->fetch();
        if (!$passage) throw new InvalidArgumentException('Passage not found.');
        return $passage;
    }

    private function passagePosition(array $passage, int $bookId, int $chapter, int $verse): array
    {
        $query = $this->db->prepare(
            'SELECT v.book_id,v.chapter,v.verse FROM bible_verses v
             JOIN translations t ON t.id=v.translation_id
             JOIN canon_books cb ON cb.canon_id=t.canon_id AND cb.book_id=v.book_id
             WHERE v.translation_id=? AND
             (cb.position>(SELECT position FROM canon_books WHERE canon_id=t.canon_id AND book_id=?) OR (v.book_id=? AND (v.chapter>? OR (v.chapter=? AND v.verse>=COALESCE(?,0))))) AND
             (cb.position<(SELECT position FROM canon_books WHERE canon_id=t.canon_id AND book_id=?) OR (v.book_id=? AND (v.chapter<? OR (v.chapter=? AND v.verse<=COALESCE(?,65535)))))
             ORDER BY cb.position,v.chapter,v.verse,v.verse_suffix'
        );
        $query->execute([$passage['translation_id'],$passage['start_book_id'],$passage['start_book_id'],$passage['start_chapter'],$passage['start_chapter'],$passage['start_verse'],$passage['end_book_id'],$passage['end_book_id'],$passage['end_chapter'],$passage['end_chapter'],$passage['end_verse']]);
        $rows = $query->fetchAll(); $position = 0;
        foreach ($rows as $index => $row) {
            if ((int)$row['book_id']===$bookId && (int)$row['chapter']===$chapter && (int)$row['verse']===$verse) $position=$index+1;
        }
        if ($position===0) throw new InvalidArgumentException('The current verse is outside this assignment.');
        return [$position,count($rows)];
    }

    private function progressRow(int $userId, string $publicId): ?array { $q=$this->db->prepare('SELECT upp.progress_percent,upp.completed_at,b.code last_book,upp.last_chapter,upp.last_verse FROM plan_passages pp LEFT JOIN user_passage_progress upp ON upp.passage_id=pp.id AND upp.user_id=? LEFT JOIN books b ON b.id=upp.last_book_id WHERE pp.public_id=?');$q->execute([$userId,$publicId]);return $q->fetch()?:null; }
    private function internalPlanId(string $publicId,int $userId): int { $q=$this->db->prepare('SELECT rp.id FROM reading_plans rp JOIN group_plans gp ON gp.plan_id=rp.id JOIN group_members gm ON gm.group_id=gp.group_id WHERE rp.public_id=? AND gm.user_id=? AND gm.status=\'active\' LIMIT 1');$q->execute([$publicId,$userId]);return (int)$q->fetchColumn(); }
    private function lastVerse(int $translationId,int $bookId,int $chapter): int { $q=$this->db->prepare('SELECT MAX(verse) FROM bible_verses WHERE translation_id=? AND book_id=? AND chapter=?');$q->execute([$translationId,$bookId,$chapter]);return (int)$q->fetchColumn(); }
}
