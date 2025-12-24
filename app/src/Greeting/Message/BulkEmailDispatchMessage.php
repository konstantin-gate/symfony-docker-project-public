<?php

declare(strict_types=1);

namespace App\Greeting\Message;

/**
 * Zpráva pro Messenger, která inicializuje hromadné rozesílání e-mailů.
 */
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
