<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Message;

final readonly class ProcessSourceMessage
{
    public function __construct(
        public int $sourceId,
    ) {
    }
}
