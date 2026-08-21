<?php

declare(strict_types=1);

namespace FeedMySheep\Bible\Provider;

use FeedMySheep\Bible\PassageReference;

interface AudioProviderInterface
{
    public function getAvailableAudioVersions(): array;
    public function getAudio(string $audioVersion, string $bookCode, int $chapter): array;
    public function getVerseTiming(string $audioVersion, PassageReference $reference): array;
}

