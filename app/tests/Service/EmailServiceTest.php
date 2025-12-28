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

/**
 * Třída pro testování služby EmailService.
 * Ověřuje odesílání e-mailů, správné nastavení odesílatele, příjemce, předmětu, šablony a kontextu.
 */
class EmailServiceTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private EmailService $service;
    private const string SENDER_EMAIL = 'noreply@example.com';
    private const string SENDER_NAME = 'Bot Name';

    /**
     * Inicializuje potřebné závislosti před každým testem.
     * Vytváří mock pro MailerInterface a instanci testované služby EmailService.
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
     * Ověřuje, že e-mail je odeslán se všemi správnými údaji.
     * Kontroluje nastavení odesílatele, příjemce, předmětu, HTML šablony a proměnných kontextu.
     *
     * @throws TransportExceptionInterface
     */
    public function testSendsTemplatedEmailWithCorrectData(): void
    {
        $to = 'recipient@example.com';
        $subject = 'Test Subject';
        $template = 'emails/test.html.twig';
        $context = ['key' => 'value'];

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) use ($to, $subject, $template, $context) {
                // Kontrola odesílatele
                $from = $email->getFrom();
                $this->assertCount(1, $from);
                $this->assertSame(self::SENDER_EMAIL, $from[0]->getAddress());
                $this->assertSame(self::SENDER_NAME, $from[0]->getName());

                // Kontrola příjemce
                $toAddresses = $email->getTo();
                $this->assertCount(1, $toAddresses);
                $this->assertSame($to, $toAddresses[0]->getAddress());

                // Kontrola předmětu
                $this->assertSame($subject, $email->getSubject());

                // Kontrola šablony
                $this->assertSame($template, $email->getHtmlTemplate());

                // Kontrola kontextu
                $this->assertSame($context, $email->getContext());

                return true;
            }));

        $this->service->send($to, $subject, $template, $context);
    }

    /**
     * Ověřuje, že e-mail lze odeslat i s prázdným kontextem.
     * Zajišťuje, že absence kontextových dat nezpůsobí chybu.
     *
     * @throws TransportExceptionInterface
     */
    public function testSendsEmailWithEmptyContext(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $this->assertSame([], $email->getContext());

                return true;
            }));

        $this->service->send('test@example.com', 'Sub', 'tpl.html.twig');
    }

    /**
     * Ověřuje, že metoda vyhodí chybu typu, pokud je příjemce předán jako pole.
     * Očekává se, že příjemce bude řetězec.
     *
     * @throws TransportExceptionInterface
     */
    public function testThrowsExceptionOnArrayRecipient(): void
    {
        $this->expectException(\TypeError::class);

        // @phpstan-ignore-next-line
        $this->service->send(['array@example.com'], 'Sub', 'tpl.html.twig');
    }

    /**
     * Ověřuje, že textová šablona není automaticky nastavena.
     * Pokud není specifikována, měla by zůstat null.
     *
     * @throws TransportExceptionInterface
     */
    public function testDoesNotSetTextTemplate(): void
    {
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                $this->assertNull($email->getTextTemplate());

                return true;
            }));

        $this->service->send('test@example.com', 'Sub', 'emails/html_only.html.twig');
    }

    /**
     * Ověřuje, že výjimky z maileru jsou správně propagovány.
     * Pokud metoda send() maileru selže, služba by měla tuto výjimku předat dál.
     */
    public function testPropagatesMailerException(): void
    {
        $this->mailer->method('send')
            ->willThrowException(new TransportException('Connection error'));

        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessage('Connection error');

        $this->service->send('any@example.com', 'Subject', 'tpl.html.twig');
    }
}