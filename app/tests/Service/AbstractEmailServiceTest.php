<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\AbstractEmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * Testovací třída pro AbstractEmailService.
 * Ověřuje základní logiku vytváření e-mailů a zpracování příjemců.
 */
class AbstractEmailServiceTest extends TestCase
{
    /**
     * Ověřuje, že metoda send() správně sestaví objekt TemplatedEmail se všemi parametry.
     * Kontroluje odesílatele, příjemce, předmět, šablonu a kontext.
     */
    public function testSendConstructsEmailCorrectly(): void
    {
        // 1. Příprava dat
        $senderEmail = 'sender@example.com';
        $senderName = 'Sender Name';
        $to = 'recipient@example.com';
        $subject = 'Test Subject';
        $template = 'email/test.html.twig';
        $context = ['foo' => 'bar'];

        // 2. Vytvoření konkrétní implementace (anonymní třída)
        // Tuto třídu používáme k zachycení objektu e-mailu, který abstraktní třída vytvoří.
        $service = new class($senderEmail, $senderName) extends AbstractEmailService {
            public ?TemplatedEmail $capturedEmail = null;

            protected function sendEmail(TemplatedEmail $email): void
            {
                $this->capturedEmail = $email;
            }
        };

        // 3. Spuštění
        $service->send($to, $subject, $template, $context);

        // 4. Ověření
        $email = $service->capturedEmail;
        $this->assertNotNull($email, 'The sendEmail method was not called.');

        // Kontrola odesílatele
        $this->assertCount(1, $email->getFrom());
        $this->assertEquals($senderEmail, $email->getFrom()[0]->getAddress());
        $this->assertEquals($senderName, $email->getFrom()[0]->getName());

        // Kontrola příjemce
        $this->assertCount(1, $email->getTo());
        $this->assertEquals($to, $email->getTo()[0]->getAddress());

        // Kontrola detailů
        $this->assertEquals($subject, $email->getSubject());
        $this->assertEquals($template, $email->getHtmlTemplate());
        $this->assertEquals($context, $email->getContext());
    }

    /**
     * Ověřuje, že metoda send() správně zpracuje řetězec s více příjemci oddělenými čárkou.
     * Zajišťuje, že se vytvoří správný počet adres a že jsou oříznuty mezery.
     */
    public function testSendHandlesMultipleRecipients(): void
    {
        // 1. Příprava
        $service = new class('sender@example.com', 'Sender') extends AbstractEmailService {
            public ?TemplatedEmail $capturedEmail = null;

            protected function sendEmail(TemplatedEmail $email): void
            {
                $this->capturedEmail = $email;
            }
        };

        // 2. Spuštění s e-maily oddělenými čárkou
        // Poznámka: Mezery kolem čárek by měla služba odstranit
        $service->send('one@example.com, two@example.com ,three@example.com', 'Subj', 'tpl');

        // 3. Ověření
        $email = $service->capturedEmail;
        $this->assertNotNull($email);

        $recipients = $email->getTo();
        $this->assertCount(3, $recipients);

        // Pomocná funkce pro extrakci adres z objektů Address
        $addresses = array_map(static fn (Address $addr) => $addr->getAddress(), $recipients);

        $this->assertContains('one@example.com', $addresses);
        $this->assertContains('two@example.com', $addresses);
        $this->assertContains('three@example.com', $addresses);
    }

    /**
     * Ověřuje chování při předání prázdného řetězce nebo mezer jako příjemce.
     * Očekává se výjimka RfcComplianceException, protože prázdná adresa není platná.
     */
    public function testSendWithEmptyToString(): void
    {
        // 1. Příprava anonymní služby
        $service = new class('sender@example.com', 'Sender') extends AbstractEmailService {
            protected function sendEmail(TemplatedEmail $email): void
            {
                // V tomto testu k odeslání nedojde kvůli výjimce dříve
            }
        };

        // 2. Očekávání výjimky
        $this->expectException(RfcComplianceException::class);

        // 3. Spuštění s prázdným řetězcem
        $service->send('', 'Subject', 'template.html.twig');
    }

    /**
     * Ověřuje chování při předání neplatného formátu e-mailové adresy.
     * Očekává se výjimka RfcComplianceException, protože Symfony Mailer vyžaduje validní formát dle RFC.
     */
    public function testSendWithInvalidEmailAddress(): void
    {
        // 1. Příprava
        $service = new class('sender@example.com', 'Sender') extends AbstractEmailService {
            protected function sendEmail(TemplatedEmail $email): void
            {
                // V tomto testu k odeslání nedojde kvůli výjimce dříve
            }
        };

        // 2. Očekávání výjimky
        $this->expectException(RfcComplianceException::class);

        // 3. Spuštění s neplatnou adresou
        $service->send('not-an-email-format', 'Subject', 'template.html.twig');
    }

    /**
     * Ověřuje, že metoda send() povolí a správně nastaví prázdný předmět e-mailu.
     * I když to není doporučeno, technicky je to možné.
     */
    public function testSendWithEmptySubject(): void
    {
        // 1. Příprava
        $service = new class('sender@example.com', 'Sender') extends AbstractEmailService {
            public ?TemplatedEmail $capturedEmail = null;

            protected function sendEmail(TemplatedEmail $email): void
            {
                $this->capturedEmail = $email;
            }
        };

        // 2. Spuštění s prázdným předmětem
        $service->send('recipient@example.com', '', 'template.html.twig');

        // 3. Ověření
        $this->assertNotNull($service->capturedEmail);
        $this->assertSame('', $service->capturedEmail->getSubject());
    }

    /**
     * Ověřuje, že metoda send() správně předá i prázdný název šablony do objektu TemplatedEmail.
     * Samotná validace existence šablony probíhá až při vykreslování v konkrétním maileru.
     */
    public function testSendWithEmptyTemplate(): void
    {
        // 1. Příprava
        $service = new class('sender@example.com', 'Sender') extends AbstractEmailService {
            public ?TemplatedEmail $capturedEmail = null;

            protected function sendEmail(TemplatedEmail $email): void
            {
                $this->capturedEmail = $email;
            }
        };

        // 2. Spuštění s prázdnou šablonou
        $service->send('recipient@example.com', 'Subject', '');

        // 3. Ověření
        $this->assertNotNull($service->capturedEmail);
        $this->assertSame('', $service->capturedEmail->getHtmlTemplate());
    }

    /**
     * Ověřuje, že předání hodnoty null do parametrů typu string vyvolá TypeError.
     * Toto zajišťuje striktní typovou kontrolu v souladu s declare(strict_types=1).
     */
    public function testThrowsTypeErrorOnNullParameters(): void
    {
        // 1. Příprava
        $service = new class('sender@example.com', 'Sender') extends AbstractEmailService {
            protected function sendEmail(TemplatedEmail $email): void
            {
                // K odeslání nedojde kvůli TypeError
            }
        };

        // 2. Očekávání výjimky TypeError
        $this->expectException(\TypeError::class);

        // 3. Spuštění s null místo stringu (potlačení statické analýzy pro účely testu)
        // @phpstan-ignore-next-line
        $service->send(null, 'Subject', 'template.html.twig');
    }

    /**
     * Ověřuje, že každý volání metody send() vytvoří nový, samostatný objekt TemplatedEmail.
     * Zajišťuje, že mezi jednotlivými odesláními nedochází k nežádoucímu sdílení stavu.
     */
    public function testMultipleCallsCreateSeparateEmailObjects(): void
    {
        // 1. Příprava služby, která ukládá všechny vytvořené e-maily
        $service = new class('sender@example.com', 'Sender') extends AbstractEmailService {
            /** @var TemplatedEmail[] */
            public array $capturedEmails = [];

            protected function sendEmail(TemplatedEmail $email): void
            {
                $this->capturedEmails[] = $email;
            }
        };

        // 2. Provedení dvou samostatných volání
        $service->send('first@example.com', 'Subject 1', 'template1.html.twig');
        $service->send('second@example.com', 'Subject 2', 'template2.html.twig');

        // 3. Ověření
        $this->assertCount(2, $service->capturedEmails);

        [$email1, $email2] = $service->capturedEmails;

        // Objekty musí být různé (ne stejná instance)
        $this->assertNotSame($email1, $email2);

        // Každý musí mít svá správná data
        $this->assertSame('Subject 1', $email1->getSubject());
        $this->assertSame('Subject 2', $email2->getSubject());
        $this->assertEquals('first@example.com', $email1->getTo()[0]->getAddress());
        $this->assertEquals('second@example.com', $email2->getTo()[0]->getAddress());
    }

    /**
     * Ověřuje, že metoda send() správně zpracuje komplexní e-mailové adresy (např. s uvozovkami).
     * Zajišťuje, že tyto adresy jsou správně předány komponentě Symfony Mime.
     */
    public function testSendWithComplexEmailAddress(): void
    {
        // 1. Příprava
        $service = new class('sender@example.com', 'Sender') extends AbstractEmailService {
            public ?TemplatedEmail $capturedEmail = null;

            protected function sendEmail(TemplatedEmail $email): void
            {
                $this->capturedEmail = $email;
            }
        };

        // Adresa s uvozovkami dle RFC 5322
        $complexAddress = '"velmi.neobvykle jmeno"@example.com';

        // 2. Provedení
        $service->send($complexAddress, 'Subject', 'template.html.twig');

        // 3. Ověření
        $this->assertNotNull($service->capturedEmail);
        $this->assertSame($complexAddress, $service->capturedEmail->getTo()[0]->getAddress());
    }

    /**
     * Ověřuje, že metoda send() zvládne zpracovat velký počet příjemců (např. 60 adres).
     * Zajišťuje, že všechny adresy jsou správně rozděleny a přidány do objektu e-mailu.
     */
    public function testSendWithManyRecipients(): void
    {
        // 1. Příprava
        $service = new class('sender@example.com', 'Sender') extends AbstractEmailService {
            public ?TemplatedEmail $capturedEmail = null;

            protected function sendEmail(TemplatedEmail $email): void
            {
                $this->capturedEmail = $email;
            }
        };

        // Generování 60 e-mailových adres
        $emails = array_map(static fn (int $i) => "user{$i}@example.com", range(1, 60));
        $toString = implode(', ', $emails);

        // 2. Provedení
        $service->send($toString, 'Subject', 'template.html.twig');

        // 3. Ověření
        $this->assertNotNull($service->capturedEmail);
        $this->assertCount(60, $service->capturedEmail->getTo());

        // Kontrola první a poslední adresy
        $recipients = $service->capturedEmail->getTo();
        $this->assertSame('user1@example.com', $recipients[0]->getAddress());
        $this->assertSame('user60@example.com', $recipients[59]->getAddress());
    }
}
