<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Greeting\Service\GreetingLogger;
use App\Message\SendEmailMessage;
use App\Service\EmailSenderInterface;
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
        private EmailSenderInterface $emailSender,
        private GreetingLogger $greetingLogger,
        private int $emailDelay,
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
        $this->emailSender->send(
            $message->to,
            $message->subject,
            $message->template,
            $message->context
        );

        // Logování odeslaného e-mailu
        $this->greetingLogger->logForEmail($message->to);

        /*
         * Záměrné zpomalení zpracování fronty (Rate Limiting).
         *
         * Tento sleep je zde nezbytný pro případy, kdy se ve frontě nahromadí větší množství zpráv
         * (např. při výpadku workeru nebo hromadném importu). Bez této prodlevy by se všechny
         * nahromaděné e-maily odeslaly okamžitě po sobě, což by mohlo vést k tomu, že cílový
         * poštovní server (nebo odesílací služba) vyhodnotí aktivitu jako SPAM a zablokuje
         * další odesílání. Tímto zajišťujeme dodržení minimálního intervalu mezi e-maily
         * i v případě "dohánění" fronty.
         */
        sleep($this->emailDelay);
    }
}
