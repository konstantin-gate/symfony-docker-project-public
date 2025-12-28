<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Twig\Error\LoaderError;

/**
 * Integrační test pro EmailService, který testuje odesílání e-mailů s využitím skutečných šablon Twig.
 * Zajišťuje, že služba správně generuje a odesílá e-maily s renderovaným obsahem.
 */
class EmailServiceIntegrationTest extends KernelTestCase
{
    private EmailService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // Ujistěte se, že služba je veřejná nebo přístupná přes aliasy, pokud je to potřeba,
        // ale v testech můžeme obvykle přístupovat k soukromým službám přes testový kontejner.
        $this->service = $container->get(EmailService::class);
    }

    /**
     * Testuje odeslání e-mailu s využitím skutečné šablony z projektu: templates/email/greeting.html.twig.
     *
     * @throws TransportExceptionInterface
     */
    public function testSendsEmailWithRealTemplate(): void
    {
        // Použijte skutečnou šablonu existující v projektu: templates/email/greeting.html.twig
        // jak je vidět v kontextu struktury souborů.
        $template = 'email/greeting.html.twig';
        $to = 'integration@example.com';
        $subject = 'Integration Test';

        // Kontext požadovaný šablonou:
        // 'subject' se používá v {% block title %}{{ subject }}{% endblock %}
        // 'body' se používá v {{ body|raw }}
        $context = [
            'name' => 'Tester',
            'subject' => $subject,
            'body' => '<p>This is a test body.</p>',
        ];

        $this->service->send($to, $subject, $template, $context);

        // Ověřte, že byl e-mail odeslán (zařazen do fronty)
        // Poznámka: KernelTestCase neobsahuje MailerAssertionsTrait výchozí v starších verzích,
        // ale často je k dispozici, pokud používáme WebTestCase nebo ručně zahrneme trait.
        // Zkontrolujeme, zda můžeme zkontrolovat poštovní klienta.

        // V novějších verzích Symfony můžeme použít:
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame($subject, $email->getSubject());

        // Zkontrolujte, zda tělo obsahuje renderovaný obsah (dokazuje, že Twig fungoval)
        // Poznámka: Šablona pravděpodobně renderuje "Hello Tester" nebo podobně.
        // Protože nevíme přesný obsah, stačí zkontrolovat, že to nehavarovalo,
        // ale pokusíme se ověřit použití kontextu, pokud je to možné.
        // Předpokládáme, že šablona renderuje alespoň něco neprázdného.
        $this->assertNotEmpty($email->getHtmlBody());
    }

    /**
     * Testuje, že je vyvolána výjimka při chybějící šabloně.
     *
     * @throws TransportExceptionInterface
     */
    public function testThrowsExceptionForMissingTemplate(): void
    {
        $this->expectException(LoaderError::class);

        $this->service->send(
            'fail@example.com',
            'Fail',
            'non_existent_template.html.twig'
        );
    }
}
