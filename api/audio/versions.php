<?php
declare(strict_types=1);
use FeedMySheep\JsonResponse;
require __DIR__.'/_init.php';JsonResponse::success(['versions'=>$audio->versions()]);
