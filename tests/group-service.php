<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/GroupService.php';

use FeedMySheep\GroupService;

$method = new ReflectionMethod(GroupService::class, 'inviteCode');
$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

for ($iteration = 0; $iteration < 100; $iteration++) {
    $code = $method->invoke(null);
    assert(strlen($code) === 4);
    assert(strspn($code, $alphabet) === 4);
    assert(GroupService::normalizeCode(strtolower($code)) === $code);
}

$source = file_get_contents(dirname(__DIR__) . '/src/GroupService.php');
assert(substr_count($source, 'INSERT INTO groups (') === 1);
assert(!str_contains($source, 'INSERT INTO group_invites'));
assert(str_contains($source, "return ['code' => \$group['join_code']"));
assert(str_contains($source, 'g.join_code_hash = :hash'));

echo "group service tests passed\n";
