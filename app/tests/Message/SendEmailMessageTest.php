<?php

declare(strict_types=1);

namespace App\Tests\Message;

use App\DTO\EmailRequest;
use App\Message\SendEmailMessage;
use PHPUnit\Framework\TestCase;

/**
 * Testy pro zprávu Messengeru SendEmailMessage.
 * Ověřuje správnou inicializaci zprávy a funkčnost tovární metody fromRequest.
 */
class SendEmailMessageTest extends TestCase
{
    /**
     * Testuje přímou inicializaci zprávy pomocí konstruktoru.
     * Ověřuje, že všechny vlastnosti (to, subject, template, context) jsou správně nastaveny.
     */
    public function testConstructor(): void
    {
        $to = 'recipient@example.com';
        $subject = 'Message Subject';
        $template = 'email/message.html.twig';
        $context = ['user' => 'John Doe'];

        $message = new SendEmailMessage($to, $subject, $template, $context);

        $this->assertSame($to, $message->to);
        $this->assertSame($subject, $message->subject);
        $this->assertSame($template, $message->template);
        $this->assertSame($context, $message->context);
    }

    /**
     * Testuje výchozí hodnoty konstruktoru zprávy.
     * Ověřuje, že pole context je ve výchozím stavu prázdné.
     */
    public function testConstructorDefaults(): void
    {
        $to = 'recipient@example.com';
        $subject = 'Message Subject';
        $template = 'email/message.html.twig';

        $message = new SendEmailMessage($to, $subject, $template);

        $this->assertSame([], $message->context);
    }

    /**
     * Testuje tovární metodu fromRequest.
     * Ověřuje, že se zpráva správně vytvoří na základě dat z DTO objektu EmailRequest.
     */
    public function testFromRequestCreatesValidMessage(): void
    {
        $request = new EmailRequest(
            'request@example.com',
            'Request Subject',
            'email/request.html.twig',
            ['id' => 123]
        );

        $message = SendEmailMessage::fromRequest($request);

        $this->assertSame($request->to, $message->to);
        $this->assertSame($request->subject, $message->subject);
        $this->assertSame($request->template, $message->template);
        $this->assertSame($request->context, $message->context);
    }
}