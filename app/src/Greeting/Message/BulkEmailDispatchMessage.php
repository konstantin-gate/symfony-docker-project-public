<?php

declare(strict_types=1);

namespace App\Greeting\Message;

readonly class BulkEmailDispatchMessage
{
    /**
     * @param string[] $contactIds
     */
    public function __construct(
        public array $contactIds,
        public string $subject,
        public string $body,
    ) {
    }
}
