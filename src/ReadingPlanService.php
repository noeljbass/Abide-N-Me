<?php

declare(strict_types=1);

namespace FeedMySheep;

use FeedMySheep\Bible\PassageReference;
use FeedMySheep\Bible\ReferenceParser;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ReadingPlanService
{
    public function __construct(private readonly PDO $db) {}

    public function listForUser(int $userId): array
    {
        $query = $this->db->prepare("SELECT rp.public_id AS id,rp.name,rp.description,rp.builder_mode,rp.status,rp.start_date,rp.timezone,g.public_id AS group_id,g.name AS group_name,gm.role='owner' AS can_manage,COUNT(pd.id) AS day_count FROM group_members gm JOIN groups g ON g.id=gm.group_id JOIN group_plans gp ON gp.group_id=g.id JOIN reading_plans rp ON rp.id=gp.plan_id LEFT JOIN plan_days pd ON pd.plan_id=rp.id WHERE gm.user_id=? AND gm.status='active' AND g.archived_at IS NULL GROUP BY rp.id,g.id,gm.role ORDER BY rp.start_date DESC,rp.created_at DESC");
        $query->execute([$userId]);
        return array_map(static function (array $row): array {
            $row['day_count'] = (int) $row['day_count'];
            $row['can_manage'] = (bool) $row['can_manage'];
            return $row;
        }, $query->fetchAll());
    }

    public function update(int $userId, array $input): array
    {
        $plan = $this->ownedGroupPlan((string) ($input['plan_id'] ?? ''), (string) ($input['group_id'] ?? ''), $userId);
        $name = Validator::string($input['name'] ?? null, 2, 150);
        $description = Validator::string($input['description'] ?? '', 0, 5000);
        $startDate = (string) ($input['start_date'] ?? '');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        if (!$name || !$date || $date->format('Y-m-d') !== $startDate) throw new InvalidArgumentException('Enter a valid plan name and start date.');

        $this->db->beginTransaction();
        try {
            $shift = (new \DateTimeImmutable($plan['start_date']))->diff($date)->format('%r%a');
            $this->db->prepare('UPDATE reading_plans SET name=?,description=?,start_date=? WHERE id=?')->execute([$name, $description ?: null, $startDate, $plan['id']]);
            if ((int) $shift !== 0) $this->db->prepare('UPDATE plan_days SET scheduled_date=DATE_ADD(scheduled_date, INTERVAL ? DAY) WHERE plan_id=?')->execute([(int) $shift, $plan['id']]);
            $this->db->commit();
            return ['id' => $input['plan_id'], 'name' => $name, 'description' => $description ?: null, 'start_date' => $startDate];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function delete(int $userId, string $publicId, string $groupPublicId): void
    {
        $plan = $this->ownedGroupPlan($publicId, $groupPublicId, $userId);
        $this->db->prepare('DELETE FROM reading_plans WHERE id=?')->execute([$plan['id']]);
    }

    public function today(int $userId): array
    {
        $query = $this->db->prepare("SELECT rp.id,rp.public_id,rp.name,rp.timezone,g.public_id group_id,g.name group_name FROM group_members gm JOIN groups g ON g.id=gm.group_id JOIN group_plans gp ON gp.group_id=g.id JOIN reading_plans rp ON rp.id=gp.plan_id WHERE gm.user_id=? AND gm.status='active' AND rp.status='active' AND g.archived_at IS NULL");
        $query->execute([$userId]);
        $result = [];
        $dayQuery = $this->db->prepare('SELECT pd.id,pd.day_number,pd.scheduled_date,pd.title,pd.note,pd.discussion_question FROM plan_days pd WHERE pd.plan_id=? AND pd.scheduled_date=?');
        $passageQuery = $this->db->prepare('SELECT pp.public_id AS id,pp.position,sb.code start_book,pp.start_chapter,pp.start_verse,eb.code end_book,pp.end_chapter,pp.end_verse,pp.display_reference,pp.estimated_read_seconds FROM plan_passages pp JOIN books sb ON sb.id=pp.start_book_id JOIN books eb ON eb.id=pp.end_book_id WHERE pp.plan_day_id=? ORDER BY pp.position');
        foreach ($query->fetchAll() as $plan) {
            $date = (new \DateTimeImmutable('now', new \DateTimeZone($plan['timezone'])))->format('Y-m-d');
            $dayQuery->execute([$plan['id'], $date]); $day = $dayQuery->fetch(); if (!$day) continue;
            $passageQuery->execute([$day['id']]); $day['day_number']=(int)$day['day_number']; $day['passages']=$passageQuery->fetchAll(); unset($day['id'],$plan['id'],$plan['timezone']);
            $result[]=['plan'=>$plan,'day'=>$day];
        }
        return $result;
    }

    public function create(int $userId, array $input): array
    {
        $name = Validator::string($input['name'] ?? null, 2, 150);
        $description = Validator::string($input['description'] ?? '', 0, 5000);
        $mode = $input['mode'] ?? '';
        $startDate = $input['start_date'] ?? '';
        $timezone = Validator::string($input['timezone'] ?? 'UTC', 1, 64);
        if (!$name || !in_array($mode, ['automatic', 'manual'], true) || !$timezone || !in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Enter a valid plan name, mode, and timezone.');
        }
        $group = $this->authorizedGroup((string) ($input['group_id'] ?? ''), $userId);
        $translation = $this->translation((string) ($input['translation'] ?? 'DRA1899'));
        $aliases = $this->aliases();
        $days = $mode === 'automatic'
            ? $this->automaticDays($input, $translation, $startDate)
            : $this->manualDays($input, $translation, $startDate, $aliases);

        $this->db->beginTransaction();
        try {
            $publicId = self::uuidV4();
            $insert = $this->db->prepare("INSERT INTO reading_plans(public_id,created_by_user_id,canon_id,default_translation_id,name,description,builder_mode,status,start_date,timezone) VALUES(?,?,?,?,?,?,?,'active',?,?)");
            $insert->execute([$publicId, $userId, $translation['canon_id'], $translation['id'], $name, $description ?: null, $mode, $startDate, $timezone]);
            $planId = (int) $this->db->lastInsertId();
            $this->db->prepare('INSERT INTO group_plans(group_id,plan_id,assigned_by_user_id,is_primary) VALUES(?,?,?,FALSE)')->execute([$group['id'], $planId, $userId]);
            $dayInsert = $this->db->prepare('INSERT INTO plan_days(plan_id,day_number,scheduled_date,title,note,discussion_question) VALUES(?,?,?,?,?,?)');
            $passageInsert = $this->db->prepare('INSERT INTO plan_passages(public_id,plan_day_id,position,start_book_id,start_chapter,start_verse,end_book_id,end_chapter,end_verse,display_reference,estimated_read_seconds) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($days as $index => $day) {
                $dayInsert->execute([$planId, $index + 1, $day['date'], $day['title'], $day['note'], $day['question']]);
                $dayId = (int) $this->db->lastInsertId();
                foreach ($day['passages'] as $position => $passage) {
                    $passageInsert->execute([self::uuidV4(), $dayId, $position + 1, $passage['start_book_id'], $passage['reference']->startChapter, $passage['reference']->startVerse, $passage['end_book_id'], $passage['reference']->endChapter, $passage['reference']->endVerse, $passage['display'], $passage['estimated_read_seconds']]);
                }
            }
            $this->db->commit();
            return ['id' => $publicId, 'name' => $name, 'mode' => $mode, 'status' => 'active', 'start_date' => $startDate, 'day_count' => count($days), 'group_id' => $input['group_id']];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function automaticDays(array $input, array $translation, string $startDate): array
    {
        $duration = Validator::positiveInteger($input['duration_days'] ?? null);
        $dates = PlanScheduler::readingDates($startDate, $duration ?? 0, is_array($input['weekdays'] ?? null) ? $input['weekdays'] : []);
        $codes = array_values(array_unique(array_filter(array_map(static fn($code) => strtoupper(trim((string) $code)), is_array($input['books'] ?? null) ? $input['books'] : []))));
        if (!$codes || count($codes) > 73) throw new InvalidArgumentException('Choose at least one Bible book.');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $query = $this->db->prepare("SELECT b.id AS book_id,b.code,b.name,v.chapter,v.verse,cb.position FROM bible_verses v JOIN books b ON b.id=v.book_id JOIN canon_books cb ON cb.book_id=b.id AND cb.canon_id=? WHERE v.translation_id=? AND b.code IN ({$placeholders}) ORDER BY cb.position,v.chapter,v.verse,v.verse_suffix");
        $query->execute([$translation['canon_id'], $translation['id'], ...$codes]);
        $units = array_map(static fn(array $row): array => ['book' => $row['code'], 'name' => $row['name'], 'book_id' => (int) $row['book_id'], 'chapter' => (int) $row['chapter'], 'verse' => (int) $row['verse'], 'verses' => 1], $query->fetchAll());
        if (!$units || count(array_unique(array_column($units, 'book'))) !== count($codes)) throw new InvalidArgumentException('One or more selected books are unavailable in this translation.');
        $buckets = PlanScheduler::distribute($units, count($dates));
        $days = [];
        foreach ($buckets as $index => $bucket) {
            $passages = [];
            $groups = [];
            foreach ($bucket as $unit) $groups[$unit['book']][] = $unit;
            foreach ($groups as $group) {
                $first = $group[0]; $last = $group[array_key_last($group)];
                if ($first['chapter'] === $last['chapter']) {
                    $display = sprintf('%s %d:%d–%d', $first['name'], $first['chapter'], $first['verse'], $last['verse']);
                } else {
                    $display = sprintf('%s %d:%d–%d:%d', $first['name'], $first['chapter'], $first['verse'], $last['chapter'], $last['verse']);
                }
                $reference = new PassageReference($first['book'], $first['chapter'], $first['verse'], $last['book'], $last['chapter'], $last['verse'], $display);
                $passages[] = ['reference' => $reference, 'start_book_id' => $first['book_id'], 'end_book_id' => $last['book_id'], 'display' => $display, 'estimated_read_seconds' => count($group) * 20];
            }
            $days[] = ['date' => $dates[$index], 'title' => null, 'note' => null, 'question' => null, 'passages' => $passages];
        }
        return $days;
    }

    private function manualDays(array $input, array $translation, string $startDate, array $aliases): array
    {
        $rawDays = is_array($input['days'] ?? null) ? $input['days'] : [];
        if (!$rawDays || count($rawDays) > 730) throw new InvalidArgumentException('Add between 1 and 730 plan days.');
        $dates = PlanScheduler::readingDates($startDate, 730, range(1, 7));
        $parser = new ReferenceParser($aliases);
        $days = [];
        foreach ($rawDays as $index => $raw) {
            $references = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($raw['passages'] ?? '')))));
            if (!$references || count($references) > 20) throw new InvalidArgumentException('Every manual day needs between 1 and 20 passages.');
            $passages = [];
            foreach ($references as $display) $passages[] = $this->validatedPassage($translation, $parser->parse($display));
            $days[] = ['date' => $dates[$index], 'title' => Validator::string($raw['title'] ?? '', 0, 150) ?: null, 'note' => Validator::string($raw['note'] ?? '', 0, 5000) ?: null, 'question' => Validator::string($raw['question'] ?? '', 0, 5000) ?: null, 'passages' => $passages];
        }
        return $days;
    }

    private function validatedPassage(array $translation, PassageReference $reference): array
    {
        $book = $this->db->prepare('SELECT code,id FROM books WHERE code IN (?,?)');
        $book->execute([$reference->startBook, $reference->endBook]);
        $ids = $book->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!isset($ids[$reference->startBook], $ids[$reference->endBook])) throw new InvalidArgumentException('A referenced book is unavailable.');
        $query = $this->db->prepare('SELECT cb.position,v.book_id,v.chapter,MIN(v.verse) first_verse,MAX(v.verse) last_verse,COUNT(*) verse_count FROM bible_verses v JOIN canon_books cb ON cb.book_id=v.book_id AND cb.canon_id=? WHERE v.translation_id=? AND ((v.book_id=? AND v.chapter=?) OR (v.book_id=? AND v.chapter=?)) GROUP BY cb.position,v.book_id,v.chapter');
        $query->execute([$translation['canon_id'],$translation['id'],$ids[$reference->startBook],$reference->startChapter,$ids[$reference->endBook],$reference->endChapter]);
        $bounds=$query->fetchAll(); if(count($bounds)<($reference->startBook===$reference->endBook&&$reference->startChapter===$reference->endChapter?1:2)) throw new InvalidArgumentException('A referenced chapter is unavailable.');
        $byBook=[]; foreach($bounds as $bound)$byBook[(string)$bound['book_id'].':'.$bound['chapter']]=$bound;
        $startBound=$byBook[$ids[$reference->startBook].':'.$reference->startChapter];$endBound=$byBook[$ids[$reference->endBook].':'.$reference->endChapter];
        if([(int)$startBound['position'],$reference->startChapter,$reference->startVerse??0] > [(int)$endBound['position'],$reference->endChapter,$reference->endVerse??65535]) throw new InvalidArgumentException('A passage must end after it starts.');
        if(($reference->startVerse!==null&&$reference->startVerse>(int)$startBound['last_verse'])||($reference->endVerse!==null&&$reference->endVerse>(int)$endBound['last_verse'])) throw new InvalidArgumentException('A referenced verse is unavailable.');
        return ['reference'=>$reference,'start_book_id'=>(int)$ids[$reference->startBook],'end_book_id'=>(int)$ids[$reference->endBook],'display'=>$reference->display,'estimated_read_seconds'=>null];
    }

    private function authorizedGroup(string $publicId, int $userId): array { $q=$this->db->prepare("SELECT g.id,gm.role FROM groups g JOIN group_members gm ON gm.group_id=g.id WHERE g.public_id=? AND gm.user_id=? AND gm.status='active' AND gm.role IN ('owner','admin') AND g.archived_at IS NULL");$q->execute([$publicId,$userId]);$group=$q->fetch();if(!$group)throw new InvalidArgumentException('Choose a group you administer.');return $group; }
    private function ownedGroupPlan(string $publicId, string $groupPublicId, int $userId): array { $q=$this->db->prepare("SELECT rp.id,rp.start_date FROM reading_plans rp JOIN group_plans gp ON gp.plan_id=rp.id JOIN groups g ON g.id=gp.group_id JOIN group_members gm ON gm.group_id=g.id WHERE rp.public_id=? AND g.public_id=? AND gm.user_id=? AND gm.status='active' AND gm.role='owner' AND g.archived_at IS NULL");$q->execute([$publicId,$groupPublicId,$userId]);$plan=$q->fetch();if(!$plan)throw new InvalidArgumentException('Only the group creator can manage this plan.');return $plan; }
    private function translation(string $code): array { $q=$this->db->prepare('SELECT id,canon_id FROM translations WHERE code=? AND is_active=TRUE');$q->execute([strtoupper($code)]);$row=$q->fetch();if(!$row)throw new InvalidArgumentException('Choose an available translation.');return ['id'=>(int)$row['id'],'canon_id'=>(int)$row['canon_id']]; }
    private function aliases(): array { $rows=$this->db->query('SELECT LOWER(bn.name),b.code FROM book_names bn JOIN books b ON b.id=bn.book_id')->fetchAll(PDO::FETCH_KEY_PAIR);return $rows; }
    private static function uuidV4(): string { $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4)); }
}
