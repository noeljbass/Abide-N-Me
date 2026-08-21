<?php

declare(strict_types=1);

namespace FeedMySheep;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final class PlanScheduler
{
    /** @return list<string> */
    public static function readingDates(string $startDate, int $durationDays, array $weekdays): array
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$start || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            throw new InvalidArgumentException('Choose a valid start date.');
        }
        if ($durationDays < 1 || $durationDays > 730) {
            throw new InvalidArgumentException('Duration must be between 1 and 730 days.');
        }
        $weekdays = array_values(array_unique(array_map('intval', $weekdays)));
        if (!$weekdays || array_diff($weekdays, range(1, 7))) {
            throw new InvalidArgumentException('Choose at least one valid reading day.');
        }
        $dates = [];
        for ($offset = 0; $offset < $durationDays; $offset++) {
            $date = $start->add(new DateInterval("P{$offset}D"));
            if (in_array((int) $date->format('N'), $weekdays, true)) {
                $dates[] = $date->format('Y-m-d');
            }
        }
        if (!$dates) {
            throw new InvalidArgumentException('The selected date range contains no reading days.');
        }
        return $dates;
    }

    /**
     * Greedily divides sequential chapters into contiguous, verse-balanced assignments.
     * @param list<array{book:string,chapter:int,verses:int}> $chapters
     * @return list<list<array{book:string,chapter:int,verses:int}>>
     */
    public static function distribute(array $chapters, int $dayCount): array
    {
        if (!$chapters || $dayCount < 1) {
            throw new InvalidArgumentException('Books and reading days are required.');
        }
        $dayCount = min($dayCount, count($chapters));
        $remainingWeight = array_sum(array_column($chapters, 'verses'));
        $result = [];
        $cursor = 0;
        for ($day = 0; $day < $dayCount; $day++) {
            $daysLeft = $dayCount - $day;
            $target = $remainingWeight / $daysLeft;
            $bucket = [];
            $weight = 0;
            $mustLeave = $daysLeft - 1;
            while ($cursor < count($chapters) - $mustLeave) {
                $candidate = max(1, (int) $chapters[$cursor]['verses']);
                if ($bucket && abs($target - $weight) <= abs($target - ($weight + $candidate))) {
                    break;
                }
                $bucket[] = $chapters[$cursor++];
                $weight += $candidate;
            }
            $result[] = $bucket;
            $remainingWeight -= $weight;
        }
        return $result;
    }
}
