<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Greeting\Service\GreetingLogger;
use App\Message\SendEmailMessage;
use App\Service\EmailService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pro zpracování zprávy SendEmailMessage.
 * Zajišťuje odeslání e-mailu prostřednictvím EmailService a následné logování.
 */
#[AsMessageHandler]
readonly class SendEmailMessageHandler
{
    public function __construct(
        private EmailService $emailService,
        private GreetingLogger $greetingLogger,
    ) {
    }

    /**
     * Zpracuje požadavek na odeslání e-mailu.
     * Odešle e-mail a zaloguje tuto událost.
     *
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
