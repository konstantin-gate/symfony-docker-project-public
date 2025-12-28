<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FileEmailService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * Integrační testy pro FileEmailService.
 * Ověřuje ukládání e-mailů do souborového systému a vykreslování šablon.
 */
class FileEmailServiceTest extends KernelTestCase
{
    private string $tempDir;
    private FileEmailService $service;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->filesystem = new Filesystem();
        // Unikátní dočasný adresář pro každý test
        $this->tempDir = sys_get_temp_dir() . '/test_mails_' . uniqid('', true);

        /** @var Environment $twig */
        $twig = $container->get(Environment::class);

        // Ruční instanciace služby s dočasným adresářem a reálným Twigem
        $this->service = new FileEmailService(
            $this->tempDir,
            $twig,
            'sender@example.com',
            'Sender Name'
        );
    }

    protected function tearDown(): void
    {
        // Úklid po testu: smazání dočasného adresáře
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * Testuje, zda konstruktor vytvoří cílový adresář, pokud neexistuje.
     */
    public function testConstructorCreatesDirectoryIfNotExists(): void
    {
        $this->assertDirectoryExists($this->tempDir . '/var/mails');
    }

    /**
     * Testuje uložení e-mailu do souboru a ověřuje jeho obsah.
     */
    public function testSendSavesEmailToFile(): void
    {
        $to = 'recipient@example.com';
        $subject = 'Test Subject';
        // Použijeme existující jednoduchou e-mailovou šablonu
        $template = 'email/base.html.twig';

        $this->service->send($to, $subject, $template);

        // Hledání vytvořeného souboru
        $files = $this->getCreatedFiles();

        $this->assertCount(1, $files, 'Měl by být vytvořen právě jeden soubor.');

        $content = file_get_contents($files[0]);

        if (false === $content) {
            $this->fail('Nepodařilo se přečíst obsah souboru.');
        }

        // Ověření hlaviček
        $this->assertStringContainsString('To: recipient@example.com', $content);
        $this->assertStringContainsString('Subject: Test Subject', $content);

        // Ověření, že tělo obsahuje HTML (z email/base.html.twig)
        $this->assertStringContainsString('</html>', $content);
    }

    /**
     * Testuje, že více příjemců oddělených čárkou je správně uloženo do souboru.
     * Ověřuje, že každý příjemce je v souboru přítomen.
     */
    public function testMultipleRecipientsAreSavedCorrectly(): void
    {
        $to = 'alice@example.com, bob@example.com ,  carol@example.com';
        $this->service->send($to, 'Multi test', 'email/base.html.twig');

        $files = $this->getCreatedFiles();
        $this->assertCount(1, $files);

        $content = file_get_contents($files[0]);

        if (false === $content) {
            $this->fail('Nepodařilo se přečíst obsah souboru.');
        }

        // Symfony Mailer obvykle ukládá více příjemců do jedné hlavičky To oddělené čárkou
        $this->assertStringContainsString('To: alice@example.com', $content);
        $this->assertStringContainsString('bob@example.com', $content);
        $this->assertStringContainsString('carol@example.com', $content);
    }

    /**
     * Testuje, že proměnné z kontextu jsou správně vykresleny v těle e-mailu.
     * Ověřuje, že Twig šablona obdrží a zobrazí data předaná v poli context.
     */
    public function testContextVariablesAreRenderedInBody(): void
    {
        $context = [
            'subject' => 'Unique Subject Variable',
            'body' => 'This is the test message body context',
        ];

        $this->service->send('user@example.com', 'Test', 'email/greeting.html.twig', $context);

        $files = $this->getCreatedFiles();
        $this->assertCount(1, $files);

        $content = file_get_contents($files[0]);

        if (false === $content) {
            $this->fail('Nepodařilo se přečíst obsah souboru.');
        }

        // Dekódování Quoted-Printable obsahu pro snadnější porovnání (zabrání problémům s lámáním řádků)
        $decodedContent = quoted_printable_decode($content);

        // Ověření, že se data z kontextu objevila v těle (díky Twig renderu)
        $this->assertStringContainsString('Unique Subject Variable', $decodedContent);
        $this->assertStringContainsString('This is the test message body context', $decodedContent);
    }

    /**
     * Testuje, že vygenerovaný e-mail obsahuje jak HTML část, tak textovou verzi.
     * Ověřuje přítomnost obou Content-Type hlaviček v MIME struktuře.
     */
    public function testBothHtmlAndTextPartsAreGenerated(): void
    {
        $this->service->send('user@example.com', 'Test', 'email/greeting.html.twig', ['subject' => 'S', 'body' => 'B']);

        $files = $this->getCreatedFiles();
        $this->assertCount(1, $files);

        $content = file_get_contents($files[0]);

        if (false === $content) {
            $this->fail('Nepodařilo se přečíst obsah souboru.');
        }

        // Ověření, že je e-mail multipart (má alternativní verze)
        $this->assertStringContainsString('Content-Type: multipart/alternative', $content);

        // Ověření přítomnosti textové části
        $this->assertStringContainsString('Content-Type: text/plain', $content);

        // Ověření přítomnosti HTML části
        $this->assertStringContainsString('Content-Type: text/html', $content);
    }

    /**
     * Testuje, že generované názvy souborů jsou unikátní i při rychlém odesílání více e-mailů.
     * Ověřuje, že nedochází ke kolizím a přepisování souborů.
     */
    public function testGeneratedFilenamesAreUnique(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->service->send('test@example.com', 'Test ' . $i, 'email/base.html.twig');
            usleep(1000);
        }

        $files = $this->getCreatedFiles();

        // Ověření, že máme přesně 5 souborů (žádný nebyl přepsán)
        $this->assertCount(5, $files);

        // Ověření unikátnosti názvů
        $names = array_map('basename', $files);
        $this->assertCount(5, array_unique($names));
    }

    /**
     * Testuje, že opakované volání metody send() vytvoří různé soubory.
     * Ověřuje, že každá zpráva je uložena samostatně a nedochází k přepisování.
     */
    public function testMultipleCallsCreateDifferentFiles(): void
    {
        $this->service->send('one@example.com', 'První', 'email/base.html.twig');
        $this->service->send('two@example.com', 'Druhá', 'email/base.html.twig');

        $files = $this->getCreatedFiles();

        // Ověření, že existují dva soubory
        $this->assertCount(2, $files);
        // Ověření, že cesty k souborům jsou různé
        $this->assertNotEquals($files[0], $files[1], 'Každý hovor musí vytvořit unikátní soubor.');
    }

    /**
     * Testuje uložení e-mailu s prázdným předmětem.
     * Ověřuje, že systém umožní uložit zprávu bez předmětu (validní chování).
     */
    public function testEmptySubjectIsSaved(): void
    {
        $this->service->send('user@example.com', '', 'email/base.html.twig');

        $files = $this->getCreatedFiles();
        $this->assertCount(1, $files);

        $content = file_get_contents($files[0]);

        if (false === $content) {
            $this->fail('Nepodařilo se přečíst obsah souboru.');
        }

        // Symfony Mailer (Mime) vynechá hlavičku Subject, pokud je prázdná
        $this->assertStringNotContainsString('Subject:', $content);
    }

    /**
     * Testuje, že při použití neexistující šablony je vyvolána výjimka Twig\Error\LoaderError.
     * Ověřuje, že BodyRenderer správně hlásí chybějící soubory.
     */
    public function testSendThrowsExceptionOnMissingTemplate(): void
    {
        $this->expectException(LoaderError::class);

        $this->service->send('test@example.com', 'Test', 'neexistujici_sablona.html.twig');
    }

    /**
     * Testuje, že neplatná e-mailová adresa vyvolá výjimku RfcComplianceException.
     * Ověřuje, že Symfony Mime komponenta správně validuje příjemce i při ukládání do souboru.
     */
    public function testInvalidEmailAddressHandling(): void
    {
        $this->expectException(RfcComplianceException::class);

        $this->service->send('neni-email', 'Subject', 'email/base.html.twig');
    }

    /**
     * Testuje, že speciální znaky a dlouhý předmět jsou správně kódovány v souboru EML.
     * Ověřuje, že diakritika v předmětu nezpůsobí chybu a je v souboru přítomna (v zakódované formě).
     */
    public function testSpecialCharactersInSubject(): void
    {
        $specialSubject = 'Příliš žluťoučký kůň úpěl ďábelské ódy - Extra dlouhý předmět pro testování kódování';

        $this->service->send('user@example.com', $specialSubject, 'email/base.html.twig');

        $files = $this->getCreatedFiles();
        $this->assertCount(1, $files);

        $content = file_get_contents($files[0]);

        if (false === $content) {
            $this->fail('Nepodařilo se přečíst obsah souboru.');
        }

        $this->assertStringContainsString('Subject:', $content);

        // Extrakce hlavičky Subject pro ověření kódování
        if (\function_exists('iconv_mime_decode') && preg_match('/Subject: (.*?)(?:\r\n|\n)/', $content, $matches)) {
            $decodedSubjectPart = iconv_mime_decode($matches[1], \ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

            if (false === $decodedSubjectPart) {
                $this->fail('Nepodařilo se dekódovat předmět e-mailu.');
            }

            // Ověříme alespoň začátek, protože zbytek může být na dalších řádcích (MIME folding)
            $this->assertStringContainsString('Příliš žluťoučký', $decodedSubjectPart);
        }
    }

    /**
     * Vrací seznam všech vytvořených .eml souborů v testovacím adresáři.
     *
     * @return string[]
     */
    private function getCreatedFiles(): array
    {
        $files = glob($this->tempDir . '/var/mails/*.eml');

        return \is_array($files) ? $files : [];
    }
}
