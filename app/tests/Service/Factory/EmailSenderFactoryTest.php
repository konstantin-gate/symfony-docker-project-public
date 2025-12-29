<?php

declare(strict_types=1);

namespace App\Tests\Service\Factory;

use App\Service\EmailSenderInterface;
use App\Service\EmailService;
use App\Service\Factory\EmailSenderFactory;
use App\Service\FileEmailService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Testy pro třídu EmailSenderFactory.
 * Ověřuje správný výběr strategie odesílání e-mailů na základě konfigurace.
 */
class EmailSenderFactoryTest extends TestCase
{
    private EmailSenderInterface&MockObject $smtpSender;
    private EmailSenderInterface&MockObject $fileSender;
    private EmailSenderFactory $factory;

    /**
     * Příprava prostředí pro testy.
     * Inicializuje mock objekty a instanci testované továrny.
     */
    protected function setUp(): void
    {
        $this->smtpSender = $this->createMock(EmailSenderInterface::class);
        $this->fileSender = $this->createMock(EmailSenderInterface::class);

        $this->factory = new EmailSenderFactory(
            $this->smtpSender,
            $this->fileSender
        );
    }

    /**
     * Ověřuje, že při zadání režimu 'smtp' továrna vrátí instanci SMTP odesílače.
     */
    public function testCreateReturnsSmtpSender(): void
    {
        $sender = $this->factory->create('smtp');

        $this->assertSame($this->smtpSender, $sender);
    }

    /**
     * Ověřuje, že při zadání režimu 'file' továrna vrátí instanci souborového odesílače.
     */
    public function testCreateReturnsFileSender(): void
    {
        $sender = $this->factory->create('file');

        $this->assertSame($this->fileSender, $sender);
    }

    /**
     * Ověřuje, že při zadání neplatného režimu továrna vyhodí výjimku InvalidArgumentException.
     */
    public function testCreateThrowsExceptionOnInvalidMode(): void
    {
        $invalidValue = 'invalid_value';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Neplatný režim doručování e-mailů: "%s". Povolené hodnoty jsou "smtp" nebo "file".', $invalidValue));

        $this->factory->create($invalidValue);
    }

    /**
     * Ověřuje, že továrna je v současnosti citlivá na velikost písmen (case-sensitive).
     * Jelikož je změna kódu zakázána, tento test dokumentuje stávající chování,
     * kdy 'SMTP' (velkými písmeny) vyhodí výjimku, protože match v PHP je case-sensitive.
     */
    public function testCreateIsCaseSensitive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Očekáváme výjimku pro 'SMTP', protože továrna vyžaduje přesně 'smtp'
        $this->factory->create('SMTP');
    }

    /**
     * Ověřuje, že při zadání prázdného řetězce továrna vyhodí výjimku InvalidArgumentException.
     */
    public function testCreateThrowsExceptionOnEmptyMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->factory->create('');
    }

    /**
     * Ověřuje, že při zadání null továrna vyhodí TypeError (díky striktní typizaci).
     */
    public function testCreateThrowsTypeErrorOnNullMode(): void
    {
        $this->expectException(\TypeError::class);
        // @phpstan-ignore-next-line - úmyslné předání neplatného typu pro test
        $this->factory->create(null);
    }

    /**
     * Ověřuje, že továrna správně selže pro jakékoli potenciální budoucí režimy, které ještě nejsou implementovány.
     * Tento test zajišťuje, že 'match' blok zůstává striktní.
     */
    #[DataProvider('futureModesProvider')]
    public function testCreateThrowsExceptionForUnimplementedFutureModes(string $futureMode): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Neplatný režim doručování e-mailů');

        $this->factory->create($futureMode);
    }

    /**
     * Poskytuje seznam testovacích dat pro neimplementované budoucí režimy doručování.
     * Tato metoda slouží jako datový zdroj pro testování striktnosti výběru v továrně.
     *
     * @return array<string, array{0: string}>
     */
    public static function futureModesProvider(): array
    {
        return [
            'API mode' => ['api'],
            'Queue mode' => ['queue'],
            'Database mode' => ['database'],
            'AWS SES mode' => ['ses'],
        ];
    }

    /**
     * Ověřuje, že parametry konstruktoru mají správné atributy #[Autowire].
     * Tato kontrola zajišťuje, že DI kontejner Symfony vloží správné implementace.
     */
    public function testConstructorHasCorrectAutowireAttributes(): void
    {
        $reflection = new \ReflectionClass(EmailSenderFactory::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor, 'Konstruktor by měl existovat.');

        $params = $constructor->getParameters();
        $this->assertCount(2, $params, 'Konstruktor by měl mít přesně 2 parametry.');

        // Kontrola prvního parametru ($smtpSender -> EmailService)
        $smtpAttr = $params[0]->getAttributes(Autowire::class);
        $this->assertCount(1, $smtpAttr, 'První parametr by měl mít atribut #[Autowire].');
        $this->assertSame(EmailService::class, $smtpAttr[0]->getArguments()['service']);

        // Kontrola druhého parametru ($fileSender -> FileEmailService)
        $fileAttr = $params[1]->getAttributes(Autowire::class);
        $this->assertCount(1, $fileAttr, 'Druhý parametr by měl mít atribut #[Autowire].');
        $this->assertSame(FileEmailService::class, $fileAttr[0]->getArguments()['service']);
    }
}
