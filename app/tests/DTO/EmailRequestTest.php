<?php

declare(strict_types=1);

namespace App\Tests\DTO;

use App\DTO\EmailRequest;
use PHPUnit\Framework\TestCase;

/**
 * Testy pro DTO objekt EmailRequest.
 * Ověřuje správné nastavení hodnot v konstruktoru a výchozí hodnoty.
 */
class EmailRequestTest extends TestCase
{
    /**
     * Testuje, zda konstruktor správně nastaví všechny předané hodnoty do veřejných vlastností.
     * Ověřuje, že 'to', 'subject', 'template' a 'context' odpovídají vstupním datům.
     */
    public function testConstructorSetsCorrectValues(): void
    {
        $to = 'test@example.com';
        $subject = 'Test Subject';
        $template = 'email/test.html.twig';
        $context = ['key' => 'value'];

        $request = new EmailRequest($to, $subject, $template, $context);

        $this->assertSame($to, $request->to);
        $this->assertSame($subject, $request->subject);
        $this->assertSame($template, $request->template);
        $this->assertSame($context, $request->context);
    }

    /**
     * Testuje výchozí hodnoty konstruktoru.
     * Ověřuje, že pokud není předán kontext, je automaticky nastaven na prázdné pole.
     */
    public function testConstructorDefaults(): void
    {
        $to = 'test@example.com';
        $subject = 'Test Subject';
        $template = 'email/test.html.twig';

        $request = new EmailRequest($to, $subject, $template);

        $this->assertSame([], $request->context);
    }
}
