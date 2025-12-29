<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Greeting\Service\GreetingLogger;
use App\Message\SendEmailMessage;
use App\MessageHandler\SendEmailMessageHandler;
use App\Service\EmailSenderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Testovací třída pro SendEmailMessageHandler.
 * Ověřuje, že je e-mail odeslán prostřednictvím EmailSender a zalogován pomocí GreetingLogger.
 */
class SendEmailMessageHandlerTest extends TestCase
{
    /**
     * Proměnná pro zachycení délky sleep() volání v testech.
     */
    public static ?int $sleepDuration = null;

    protected function tearDown(): void
    {
        parent::tearDown();
        self::$sleepDuration = null;
    }

    /**
     * Ověřuje, že handler správně zpracuje zprávu: odešle e-mail a zaloguje tuto událost.
     *
     * @throws TransportExceptionInterface
     */
    public function testInvokeSendsEmailAndLogs(): void
    {
        // 1. Příprava dat
        $to = 'test@example.com';
        $subject = 'Test Subject';
        $template = 'email/test.html.twig';
        $context = ['key' => 'value'];
        $message = new SendEmailMessage($to, $subject, $template, $context);

        // 2. Mockování závislostí
        $emailSender = $this->createMock(EmailSenderInterface::class);
        $emailSender->expects($this->once())
            ->method('send')
            ->with($to, $subject, $template, $context);

        $greetingLogger = $this->createMock(GreetingLogger::class);
        $greetingLogger->expects($this->once())
            ->method('logForEmail')
            ->with($to);

        // 3. Inicializace handleru
        // Nastavíme zpoždění na 0, abychom při testech nečekali
        $handler = new SendEmailMessageHandler($emailSender, $greetingLogger, 0);

        // 4. Vykonání akce
        $handler($message);
    }

    /**
     * Ověřuje, že pokud odeslání e-mailu selže (vyhodí TransportExceptionInterface),
     * handler chybu nepolapí (předá ji dál) a neprovede logování.
     *
     * @throws TransportExceptionInterface
     */
    public function testRethrowsTransportException(): void
    {
        // 1. Příprava dat
        $message = new SendEmailMessage(
            'test@example.com',
            'Subject',
            'template.html.twig',
            []
        );

        // 2. Mockování závislostí
        $emailSender = $this->createMock(EmailSenderInterface::class);
        $emailSender->method('send')
            ->willThrowException($this->createMock(TransportExceptionInterface::class));

        $greetingLogger = $this->createMock(GreetingLogger::class);
        // Ověřujeme, že se logování NESPUSTÍ, pokud odeslání selže
        $greetingLogger->expects($this->never())->method('logForEmail');

        // 3. Inicializace handleru
        $handler = new SendEmailMessageHandler($emailSender, $greetingLogger, 0);

        // 4. Očekáváme vyhození výjimky
        $this->expectException(TransportExceptionInterface::class);

        // 5. Vykonání akce
        $handler($message);
    }

    /**
     * Ověřuje, že logování se neprovede, pokud odesílání e-mailu selže s obecnou chybou.
     * To je důležité pro zachování konzistence stavu systému.
     *
     * @throws \Throwable
     */
    public function testDoesNotLogWhenSendingFails(): void
    {
        // 1. Příprava dat
        $message = new SendEmailMessage('test@example.com', 'Test', 'tpl.twig', []);

        // 2. Mockování závislostí
        $emailSender = $this->createMock(EmailSenderInterface::class);
        $emailSender->method('send')
            ->willThrowException(new \RuntimeException('Sending failed'));

        $greetingLogger = $this->createMock(GreetingLogger::class);
        // Ověřujeme, že se logování NIKDY nespustí při chybě
        $greetingLogger->expects($this->never())->method('logForEmail');

        // 3. Inicializace handleru
        $handler = new SendEmailMessageHandler($emailSender, $greetingLogger, 0);

        // 4. Očekáváme, že jakákoliv Throwable chyba bude prohozena dál
        $this->expectException(\Throwable::class);

        // 5. Vykonání akce
        $handler($message);
    }

    /**
     * Ověřuje, že handler aplikuje zpoždění (sleep) mezi odesíláním e-mailů.
     * To je klíčové pro rate limiting.
     *
     * @throws TransportExceptionInterface
     */
    public function testAppliesDelayBetweenMessages(): void
    {
        // 1. Příprava dat
        $message = new SendEmailMessage('test@example.com', 'Sub', 'tpl', []);

        // 2. Mockování závislostí (stačí dummy)
        $emailSender = $this->createMock(EmailSenderInterface::class);
        $greetingLogger = $this->createMock(GreetingLogger::class);

        // 3. Inicializace handleru s nastaveným zpožděním (např. 5 sekund)
        $delay = 5;
        $handler = new SendEmailMessageHandler($emailSender, $greetingLogger, $delay);

        // 4. Vykonání akce
        $handler($message);

        // 5. Ověření
        // Zkontrolujeme, zda byla zavolána naše přetížená funkce sleep() s očekávanou hodnotou
        $this->assertSame($delay, self::$sleepDuration, 'Funkce sleep() nebyla zavolána se správnou hodnotou.');
    }

    /**
     * Ověřuje, jak handler zpracovává nulové a záporné zpoždění.
     * Nulové zpoždění musí být akceptováno, zatímco záporné by mělo vyvolat chybu ValueError,
     * což simuluje standardní chování PHP a chrání před nekorektní konfigurací.
     *
     * @throws TransportExceptionInterface
     */
    public function testHandlesZeroOrNegativeDelay(): void
    {
        $message = new SendEmailMessage('test@example.com', 'Sub', 'tpl', []);
        $emailSender = $this->createMock(EmailSenderInterface::class);
        $greetingLogger = $this->createMock(GreetingLogger::class);

        // Test nulového zpoždění - mělo by projít v pořádku
        $handlerZero = new SendEmailMessageHandler($emailSender, $greetingLogger, 0);

        $handlerZero($message);
        $this->assertSame(0, self::$sleepDuration);

        // Test záporného zpoždění - očekáváme ValueError (standardní chování PHP od verze 8.0)
        $this->expectException(\ValueError::class);
        $handlerNegative = new SendEmailMessageHandler($emailSender, $greetingLogger, -1);
        $handlerNegative($message);
    }

    /**
     * Ověřuje chování handleru při předání prázdné nebo neplatné e-mailové adresy.
     * Handler by měl chybu z EmailSenderu nechat probublat a nezalogovat úspěšné odeslání.
     *
     * @throws TransportExceptionInterface
     */
    public function testWithEmptyOrInvalidToAddress(): void
    {
        // 1. Příprava zprávy s neplatnou adresou
        $message = new SendEmailMessage('', 'Subject', 'tpl.twig', []);

        // 2. Mockování: EmailSender vyhodí výjimku, protože adresa je prázdná
        $emailSender = $this->createMock(EmailSenderInterface::class);
        $emailSender->method('send')
            ->willThrowException(new \InvalidArgumentException('Recipient address cannot be empty'));

        $greetingLogger = $this->createMock(GreetingLogger::class);
        $greetingLogger->expects($this->never())->method('logForEmail');

        // 3. Inicializace
        $handler = new SendEmailMessageHandler($emailSender, $greetingLogger, 0);

        // 4. Očekáváme výjimku
        $this->expectException(\InvalidArgumentException::class);

        // 5. Vykonání
        $handler($message);
    }

    /**
     * Ověřuje, že handler správně zpracuje zprávu s prázdným kontextem.
     * Toto je nejčastější scénář použití, kdy nejsou vyžadována žádná doplňková data pro šablonu.
     *
     * @throws TransportExceptionInterface
     */
    public function testHandlesEmptyContext(): void
    {
        // 1. Příprava dat - kontext ponecháme prázdný (výchozí hodnota)
        $to = 'empty-context@example.com';
        $subject = 'No Context';
        $template = 'simple.html.twig';
        $message = new SendEmailMessage($to, $subject, $template);

        // 2. Mockování závislostí
        $emailSender = $this->createMock(EmailSenderInterface::class);
        $emailSender->expects($this->once())
            ->method('send')
            ->with($to, $subject, $template, []); // Očekáváme prázdné pole

        $greetingLogger = $this->createMock(GreetingLogger::class);
        $greetingLogger->expects($this->once())->method('logForEmail')->with($to);

        // 3. Inicializace
        $handler = new SendEmailMessageHandler($emailSender, $greetingLogger, 0);

        // 5. Vykonání
        $handler($message);
    }

    /**
     * Ověřuje, že systém vyhodí TypeError, pokud se pokusíme vytvořit zprávu s null hodnotami v povinných polích.
     * Toto zajišťuje ochranu proti chybám v kódu, který zprávu vytváří, ještě předtím, než se dostane do handleru.
     */
    public function testThrowsTypeErrorOnNullRequiredFields(): void
    {
        // Očekáváme chybu typu (TypeError), protože pole v SendEmailMessage nejsou nullable
        $this->expectException(\TypeError::class);

        // Pokus o vytvoření zprávy s neplatným typem (null místo string)
        /* @phpstan-ignore-next-line */
        new SendEmailMessage(null, 'Subject', 'template.html.twig');
    }
}

namespace App\MessageHandler;

use App\Tests\MessageHandler\SendEmailMessageHandlerTest;

/**
 * Přetížení vestavěné funkce sleep v jmenném prostoru App\MessageHandler.
 * Toto nám umožňuje zachytit volání sleep() uvnitř SendEmailMessageHandler během testů
 * a simulovat reálné chování PHP (vyhození ValueError při záporné hodnotě).
 */
function sleep(int $seconds): int
{
    if ($seconds < 0) {
        throw new \ValueError('sleep(): Argument #1 ($seconds) must be greater than or equal to 0');
    }

    SendEmailMessageHandlerTest::$sleepDuration = $seconds;

    return 0;
}
