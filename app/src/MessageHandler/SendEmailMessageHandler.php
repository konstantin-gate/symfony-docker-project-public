<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Greeting\Service\GreetingLogger;
use App\Message\SendEmailMessage;
use App\Service\EmailService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SendEmailMessageHandler
{
    public function __construct(
        private EmailService $emailService,
        private GreetingLogger $greetingLogger,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function __invoke(SendEmailMessage $message): void
    {
        $this->emailService->send(
            $message->to,
            $message->subject,
            $message->template,
            $message->context
        );

        // Logování odeslaného e-mailu
        $this->greetingLogger->logForEmail($message->to);
    }
}
