<?php
declare(strict_types=1);
use FeedMySheep\JsonResponse;
require __DIR__ . '/_init.php';
JsonResponse::success(['canon' => 'catholic-73', 'books' => $catalog->books()]);

