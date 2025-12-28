<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\EmailService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Twig\Error\LoaderError;

/**
 * Třída pro testování služby EmailService.
 * Ověřuje integraci s AbstractEmailService a správné volání MailerInterface.
 */
class EmailServiceTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private EmailService $service;
    private const string SENDER_EMAIL = 'noreply@example.com';
    private const string SENDER_NAME = 'Bot Name';

    /**
     * Inicializuje závislosti. Vytváří mock MailerInterface a instanci EmailService.
     */
    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->service = new EmailService(
            $this->mailer,
            self::SENDER_EMAIL,
            self::SENDER_NAME
        );
    }

    /**
     * Testuje, že metoda send() správně nakonfiguruje TemplatedEmail (včetně dat z AbstractEmailService)
     * a předá jej do MailerInterface.
     */
    public function testSendSendsCorrectlyConfiguredEmail(): void
    {
        $to = 'recipient@example.com';
        $subject = 'Test Subject';
        $template = 'emails/test.html.twig';
        $context = ['key' => 'value'];

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) use ($to, $subject, $template, $context) {
                // Ověření odesílatele (nastaveného v AbstractEmailService)
                $from = $email->getFrom();
                $this->assertCount(1, $from);
                $this->assertSame(self::SENDER_EMAIL, $from[0]->getAddress());
                $this->assertSame(self::SENDER_NAME, $from[0]->getName());

                // Ověření příjemce
                $toAddresses = $email->getTo();
                $this->assertCount(1, $toAddresses);
                $this->assertSame($to, $toAddresses[0]->getAddress());

                // Ověření předmětu a šablony
                $this->assertSame($subject, $email->getSubject());
                $this->assertSame($template, $email->getHtmlTemplate());

                // Ověření kontextu
                $this->assertSame($context, $email->getContext());

                return true;
            }));

        $this->service->send($to, $subject, $template, $context);
    }

    /**
     * Testuje odeslání e-mailu na více adres příjemců (oddělených čárkou).
     * Ověřuje, že MailerInterface obdrží objekt se všemi zadanými adresami.
     */
    public function testSendWithMultipleRecipients(): void
    {
        $to = 'first@example.com, second@example.com';

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $toAddresses = $email->getTo();
                $this->assertCount(2, $toAddresses);
                $this->assertSame('first@example.com', $toAddresses[0]->getAddress());
                $this->assertSame('second@example.com', $toAddresses[1]->getAddress());

                return true;
            }));

        $this->service->send($to, 'Subject', 'tpl.html.twig');
    }

    /**
     * Testuje, že e-mail neobsahuje žádné nechtěné kopie (CC) nebo skryté kopie (BCC).
     * Ověřuje, že tyto hlavičky zůstávají ve výchozím nastavení prázdné.
     */
    public function testSendWithCcAndBcc(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $this->assertEmpty($email->getCc(), 'Hlavička CC by měla být prázdná.');
                $this->assertEmpty($email->getBcc(), 'Hlavička BCC by měla být prázdná.');

                return true;
            }));

        $this->service->send('user@example.com', 'Test', 'test.twig');
    }

    /**
     * Testuje odeslání e-mailu bez předání kontextu.
     * Ověřuje, že se použije výchozí hodnota (prázdné pole).
     */
    public function testSendWithoutContext(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(fn (TemplatedEmail $email) => $email->getContext() === []));

        $this->service->send('user@example.com', 'Hello', 'hello.twig');
    }

    /**
     * Testuje, že neplatná e-mailová adresa vyvolá výjimku RfcComplianceException.
     * Ověřuje, že systém správně validuje formát e-mailu před pokusem o odeslání.
     */
    public function testSendWithInvalidEmailAddress(): void
    {
        $this->expectException(RfcComplianceException::class);

        $this->service->send('neplatny-email', 'Subject', 'tpl.html.twig');
    }

    /**
     * Testuje chování při předání neexistující šablony.
     * Ověřuje, že výjimka z vykreslovacího jádra (nebo maileru) je správně propagována.
     */
    public function testSendWithNonExistentTemplate(): void
    {
        $this->mailer->method('send')
            ->willThrowException(new LoaderError('Template "missing.html.twig" not found.'));

        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage('not found');

        $this->service->send('user@example.com', 'Subject', 'missing.html.twig');
    }

    /**
     * Testuje odeslání e-mailu s prázdným předmětem.
     * Ověřuje, že systém dovolí odeslat e-mail i bez předmětu a správně jej předá maileru.
     */
    public function testSendWithEmptySubject(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(fn (TemplatedEmail $email) => $email->getSubject() === ''));

        $this->service->send('user@example.com', '', 'test.twig');
    }

    /**
     * Testuje, že i jiné obecné výjimky z maileru (např. RuntimeException) jsou správně propagovány.
     */
    public function testSendThrowsOtherMailerExceptions(): void
    {
        $this->mailer->method('send')
            ->willThrowException(new \RuntimeException('Neočekávaná chyba'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Neočekávaná chyba');

        $this->service->send('any@example.com', 'Subject', 'tpl.html.twig');
    }

    /**
     * Testuje, že každý příkaz send() vytvoří novou instanci TemplatedEmail.
     * Ověřuje, že nedochází k nechtěnému sdílení objektu mezi voláními.
     */
    public function testSameEmailObjectNotReusedBetweenCalls(): void
    {
        $capturedEmails = [];

        $this->mailer->expects($this->exactly(2))
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) use (&$capturedEmails) {
                $capturedEmails[] = $email;

                return true;
            }));

        $this->service->send('first@example.com', 'Sub1', 'tpl1.twig');
        $this->service->send('second@example.com', 'Sub2', 'tpl2.twig');

        $this->assertCount(2, $capturedEmails);
        $this->assertNotSame($capturedEmails[0], $capturedEmails[1], 'Instance TemplatedEmail musí být pro každé volání unikátní.');
    }

    /**
     * Testuje, že výjimka TransportExceptionInterface je správně propagována.
     */
    public function testSendThrowsExceptionOnTransportFailure(): void
    {
        $this->mailer->method('send')
            ->willThrowException(new TransportException('Transport failed'));

        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessage('Transport failed');

        $this->service->send('any@example.com', 'Subject', 'tpl.html.twig');
    }
}
