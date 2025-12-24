<?php

declare(strict_types=1);

namespace App\Message;

use App\DTO\EmailRequest;

/**
 * Zpráva pro Messenger reprezentující požadavek na odeslání jednoho e-mailu.
 */
readonly class SendEmailMessage
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $to,
        public string $subject,
        public string $template,
        public array $context = [],
    ) {
    }

    /**
     * Vytvoří novou instanci zprávy na základě DTO požadavku.
     */
    public static function fromRequest(EmailRequest $request): self
    {
        return new self(
            $request->to,
            $request->subject,
            $request->template,
            $request->context
        );
    }
}
