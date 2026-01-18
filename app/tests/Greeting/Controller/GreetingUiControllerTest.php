<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Controller;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Entity\GreetingLog;
use App\Greeting\Enum\GreetingLanguage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Třída GreetingUiControllerTest obsahuje testy pro kontroler GreetingUiController.
 * Testuje funkčnost uživatelského rozhraní pro správu pozdravů, včetně importu kontaktů,
 * odesílání e-mailů a správy jednotlivých kontaktů.
 *
 * @author Konstantin Gate
 */
class GreetingUiControllerTest extends WebTestCase
{
    /**
     * Vytvoří nový kontakt s daným e-mailovým adresou a jazykem.
     * Kontakt je uložen do databáze a vrácen jeho ID.
     *
     * @param string $email E-mailová adresa kontaktu
     * @param string $lang  Jazyk kontaktu (defaultně 'en')
     *
     * @return string ID vytvořeného kontaktu
     */
    private function createContact(string $email, string $lang = 'en'): string
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $contact = new GreetingContact();
        $contact->setEmail($email);
        $contact->setCreatedAt(new \DateTimeImmutable());
        $contact->setLanguage(GreetingLanguage::from($lang));

        $em->persist($contact);
        $em->flush();

        return (string) $contact->getId();
    }

    /**
     * Testuje, zda je dashboard přístupný a obsahuje formulář pro import kontaktů.
     * Zkontroluje, že stránka vrátí úspěšný HTTP status a že formulář pro import je přítomen.
     */
    public function testDashboardIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ru/greeting/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="greeting_import"]');
    }

    /**
     * Testuje, že při odeslání prázdného formuláře pro import kontaktů
     * uživatel je přesměrován zpět na dashboard a zobrazí se chybová zpráva.
     */
    public function testImportRedirectsAndShowsFlashOnInvalidData(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $button = $crawler->selectButton('greeting_import[import]');
        self::assertCount(1, $button, 'Import button not found');
        $form = $button->form();
        // Odeslání prázdného formuláře
        $client->submit($form);

        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        // Zkontroluje, zda je zobrazena chybová zpráva (alert-danger)
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * Testuje úspěšný import kontaktů z textového obsahu.
     * Zkontroluje, že po odeslání formuláře s platnými daty je kontakt úspěšně importován
     * a zobrazena je úspěšná zpráva.
     */
    public function testImportTextContentSuccess(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $button = $crawler->selectButton('greeting_import[import]');
        self::assertCount(1, $button, 'Import button not found');
        $form = $button->form();

        $form['greeting_import[language]'] = 'en';
        $form['greeting_import[emails]'] = 'test@example.com';

        $client->submit($form);

        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        // Zkontroluje, zda je zobrazena úspěšná zpráva (alert-success)
        self::assertSelectorExists('.alert-success');
    }

    /**
     * Testuje úspěšný import kontaktů z XML souboru.
     * Zkontroluje, že kontakt je správně uložen do databáze a zobrazena je úspěšná zpráva.
     */
    public function testImportXmlFileSuccess(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $form = $crawler->selectButton('greeting_import[import]')->form();

        $uniqueEmail = 'xml_test_' . uniqid('', true) . '@example.com';
        // Vytvoření dočasného XML souboru
        $xmlContent = <<<XML
<contacts>
    <contact>
        <email>{$uniqueEmail}</email>
    </contact>
</contacts>
XML;
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid('', true) . '.xml';
        file_put_contents($tempFile, $xmlContent);

        try {
            $form['greeting_import[language]'] = 'en';
            $form['greeting_import[xmlFile]'] = $tempFile;

            $client->submit($form);

            self::assertResponseRedirects('/ru/greeting/dashboard');
            $client->followRedirect();

            self::assertSelectorExists('.alert-success');

            // Ověření uložení kontaktu
            /** @var EntityManagerInterface $em */
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $contact = $em->getRepository(GreetingContact::class)->findOneBy(['email' => $uniqueEmail]);
            self::assertNotNull($contact);
            self::assertSame($uniqueEmail, $contact->getEmail());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Testuje import kontaktů z kombinace XML souboru a textového obsahu s duplikáty.
     * Zkontroluje, že jsou uloženy pouze jedinečné kontakty a zobrazena je správná zpráva.
     */
    public function testImportCombinedXmlAndTextWithDuplicates(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $form = $crawler->selectButton('greeting_import[import]')->form();

        $duplicateEmail = 'shared_' . uniqid('', true) . '@example.com';
        $xmlOnlyEmail = 'xml_only_' . uniqid('', true) . '@example.com';
        $textOnlyEmail = 'text_only_' . uniqid('', true) . '@example.com';

        // XML s sdíleným a jedinečným e-mailem
        $xmlContent = <<<XML
<contacts>
    <contact>
        <email>{$duplicateEmail}</email>
    </contact>
    <contact>
        <email>{$xmlOnlyEmail}</email>
    </contact>
</contacts>
XML;
        $tempFile = sys_get_temp_dir() . '/test_combined_' . uniqid('', true) . '.xml';
        file_put_contents($tempFile, $xmlContent);

        try {
            $form['greeting_import[language]'] = 'en';
            $form['greeting_import[xmlFile]'] = $tempFile;
            $form['greeting_import[emails]'] = $duplicateEmail . "\n" . $textOnlyEmail;

            $client->submit($form);

            self::assertResponseRedirects('/ru/greeting/dashboard');
            $client->followRedirect();

            self::assertSelectorExists('.alert-success');
            // Očekáváme 3 jedinečné importy: 1 sdílený + 1 pouze XML + 1 pouze text
            self::assertSelectorTextContains('.alert-success', '3');

            // Ověření uložení kontaktů
            /** @var EntityManagerInterface $em */
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $repo = $em->getRepository(GreetingContact::class);

            self::assertNotNull($repo->findOneBy(['email' => $duplicateEmail]));
            self::assertNotNull($repo->findOneBy(['email' => $xmlOnlyEmail]));
            self::assertNotNull($repo->findOneBy(['email' => $textOnlyEmail]));
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Testuje, že import neúspěšný při neplatném XML souboru.
     * Zkontroluje, že žádné kontakty nejsou uloženy a zobrazena je chybová zpráva.
     */
    public function testImportInvalidXmlFileFails(): void
    {
        $client = static::createClient();

        // Získáme počáteční počet kontaktů pro ověření, že se nic nezmění
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repo = $em->getRepository(GreetingContact::class);
        $initialCount = $repo->count([]);

        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $form = $crawler->selectButton('greeting_import[import]')->form();

        // Neplatný obsah XML (chybějící uzavírací tag pro email)
        $xmlContent = '<contacts><email>broken@example.com</contacts>';
        $tempFile = sys_get_temp_dir() . '/test_invalid_' . uniqid('', true) . '.xml';
        file_put_contents($tempFile, $xmlContent);

        try {
            $form['greeting_import[language]'] = 'en';
            $form['greeting_import[xmlFile]'] = $tempFile;

            $client->submit($form);

            self::assertResponseRedirects('/ru/greeting/dashboard');
            $client->followRedirect();

            // Zkontroluje, zda je zobrazena chybová zpráva (alert-danger)
            self::assertSelectorExists('.alert-danger');

            // Ověření, že počet kontaktů se nezměnil
            self::assertSame($initialCount, $repo->count([]));
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Testuje úspěšné odeslání pozdravu vybranému kontaktu.
     * Zkontroluje, že e-mail je zařazen do fronty a zobrazena je úspěšná zpráva.
     */
    public function testSendGreetingSuccessfully(): void
    {
        $client = static::createClient();
        $email = 'send_test_' . uniqid('', true) . '@example.com';
        $id = $this->createContact($email);

        $crawler = $client->request('GET', '/ru/greeting/dashboard');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        // Ověří existence zaškrtávacího políčka pro kontakt
        self::assertSelectorExists('input[name="contacts[]"][value="' . $id . '"]');

        // Obchází validaci DomCrawler pro velké seznamy kontaktů
        $client->request('POST', '/ru/greeting/send', [
            'contacts' => [$id],
            'subject' => 'Test Subject',
            'body' => 'Test Body',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        self::assertSelectorExists('.alert-success');
    }

    /**
     * Testuje odeslání pozdravů několika kontaktům s různými jazyky.
     * Zkontroluje, že e-maily jsou zařazeny do fronty pro každý kontakt a zobrazena je úspěšná zpráva.
     */
    public function testSendToMultipleContactsWithDifferentLanguages(): void
    {
        $client = static::createClient();
        $idEn = $this->createContact('en_multi_' . uniqid('', true) . '@example.com', 'en');
        $idRu = $this->createContact('ru_multi_' . uniqid('', true) . '@example.com', 'ru');

        // Spustí relaci
        $crawler = $client->request('GET', '/ru/greeting/dashboard');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/ru/greeting/send', [
            'contacts' => [$idEn, $idRu],
            'subject' => 'Universal Subject',
            'body' => 'Universal Body',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        self::assertSelectorExists('.alert-success');
        // Očekáváme úspěšnou zprávu, že 2 e-maily byly zařazeny do fronty/odeslány
        self::assertSelectorTextContains('.alert-success', '2');
    }

    /**
     * Testuje, že při pokusu o odeslání pozdravu bez výběru kontaktů
     * se zobrazí chybová zpráva.
     */
    public function testSendWithNoContactsShowsError(): void
    {
        $client = static::createClient();
        // Spustí relaci
        $crawler = $client->request('GET', '/ru/greeting/dashboard');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/ru/greeting/send', [
            'contacts' => [],
            'subject' => 'Subject',
            'body' => 'Body',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        // Kontroler používá 'error' flash, který se zobrazí jako alert-danger
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * Testuje smazání kontaktu přes JSON požadavek.
     * Zkontroluje, že kontakt je úspěšně smazán a vrácena je odpověď s úspěchem.
     *
     * @throws \JsonException
     */
    public function testDeleteContactJson(): void
    {
        $client = static::createClient();
        $email = 'delete_test_' . uniqid('', true) . '@example.com';
        $id = $this->createContact($email);

        $client->jsonRequest('DELETE', '/ru/greeting/contact/' . $id . '/delete');

        self::assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(
            (string) json_encode(['success' => true], \JSON_THROW_ON_ERROR),
            (string) $client->getResponse()->getContent()
        );
    }

    /**
     * Testuje pokus o smazání neexistujícího kontaktu.
     * Zkontroluje, že je vrácen HTTP status 404 a odpověď s neúspěchem.
     *
     * @throws \JsonException
     */
    public function testDeleteContactNotFound(): void
    {
        $client = static::createClient();
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        $client->jsonRequest('DELETE', '/ru/greeting/contact/' . $nonExistentId . '/delete');

        self::assertResponseStatusCodeSame(404);
        $this->assertJsonStringEqualsJsonString(
            (string) json_encode(['success' => false], \JSON_THROW_ON_ERROR),
            (string) $client->getResponse()->getContent()
        );
    }

    /**
     * Testuje deaktivaci kontaktu přes JSON požadavek.
     * Zkontroluje, že kontakt je úspěšně deaktivován a vrácena je odpověď s úspěchem.
     *
     * @throws \JsonException
     */
    public function testDeactivateContactJson(): void
    {
        $client = static::createClient();
        $email = 'deactivate_test_' . uniqid('', true) . '@example.com';
        $id = $this->createContact($email);

        $client->jsonRequest('POST', '/ru/greeting/contact/' . $id . '/deactivate');

        self::assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(
            (string) json_encode(['success' => true], \JSON_THROW_ON_ERROR),
            (string) $client->getResponse()->getContent()
        );
    }

    /**
     * Testuje pokus o smazání již smazaného kontaktu.
     * Zkontroluje, že je vrácena úspěšná odpověď a zobrazena je varovná zpráva.
     *
     * @throws \JsonException
     */
    public function testDeleteAlreadyDeletedShowsWarning(): void
    {
        $client = static::createClient();
        $id = $this->createContact('deleted_warn_test_' . uniqid('', true) . '@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contact = $em->getRepository(GreetingContact::class)->find($id);
        self::assertNotNull($contact);
        $contact->setStatus(Status::Deleted);
        $em->flush();

        $client->jsonRequest('DELETE', '/ru/greeting/contact/' . $id . '/delete');

        self::assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(
            (string) json_encode(['success' => true], \JSON_THROW_ON_ERROR),
            (string) $client->getResponse()->getContent()
        );

        // Následuje "reload" požadavkem na dashboard a kontroluje varovnou zprávu
        $client->request('GET', '/ru/greeting/dashboard');
        self::assertSelectorExists('.alert-warning');
    }

    /**
     * Testuje pokus o deaktivaci již neaktivního kontaktu.
     * Zkontroluje, že je vrácena úspěšná odpověď a zobrazena je varovná zpráva.
     *
     * @throws \JsonException
     */
    public function testDeactivateAlreadyInactiveShowsWarning(): void
    {
        $client = static::createClient();
        $id = $this->createContact('inactive_warn_test_' . uniqid('', true) . '@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contact = $em->getRepository(GreetingContact::class)->find($id);
        self::assertNotNull($contact);
        $contact->setStatus(Status::Inactive);
        $em->flush();

        $client->jsonRequest('POST', '/ru/greeting/contact/' . $id . '/deactivate');

        self::assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(
            (string) json_encode(['success' => true], \JSON_THROW_ON_ERROR),
            (string) $client->getResponse()->getContent()
        );

        // Následuje "reload" požadavkem na dashboard a kontroluje varovnou zprávu
        $client->request('GET', '/ru/greeting/dashboard');
        self::assertSelectorExists('.alert-warning');
    }

    /**
     * Testuje generování testovacích e-mailů v vývojovém prostředí.
     * Zkontroluje, že jsou vráceny platné e-mailové adresy.
     */
    public function testGenerateTestEmailsInDevReturnsEmails(): void
    {
        // Ve výchozím nastavení static::createClient() používá prostředí 'test',
        // které NENÍ 'prod', takže kontrola v kontroleru to povoluje.
        $client = static::createClient();
        $client->request('GET', '/greeting/generate-test-emails');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('@', $content);
        self::assertCount(10, explode(' ', trim($content)));
    }

    /**
     * Testuje, že při odeslání prázdného formuláře v anglickém rozhraní
     * se zobrazí chybová zpráva v angličtině.
     */
    public function testImportInEnglishLocaleShowsEnglishFlash(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/greeting/dashboard');

        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('greeting_import[import]')->form();
        // Odeslání prázdného formuláře pro vyvolání chyby validace
        $client->submit($form);

        self::assertResponseRedirects('/en/greeting/dashboard');
        $client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Please provide either a list of emails or an XML file');
    }

    /**
     * Testuje, že při odeslání prázdného formuláře v českém rozhraní
     * se zobrazí chybová zpráva v češtině.
     */
    public function testImportInCzechLocaleShowsCzechFlash(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/cs/greeting/dashboard');

        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('greeting_import[import]')->form();
        $client->submit($form);

        self::assertResponseRedirects('/cs/greeting/dashboard');
        $client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Prosím zadejte seznam e-mailů nebo nahrajte XML soubor');
    }

    /**
     * Testuje, že na dashboardu se zobrazí ikona "odesláno" pro kontakty, kterým byl odeslán pozdrav.
     */
    public function testDashboardShowsSentStatusMarkerForGreetedContacts(): void
    {
        $client = static::createClient();
        $email = 'greeted_' . uniqid('', true) . '@example.com';
        $idStr = $this->createContact($email);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contact = $em->getRepository(GreetingContact::class)->find($idStr);
        self::assertNotNull($contact);

        // Vytvoření záznamu v logu, který indikuje, že pozdrav byl odeslán
        $log = new GreetingLog($contact, (int) date('Y'));
        $em->persist($log);
        $em->flush();

        $client->request('GET', '/ru/greeting/dashboard');

        // Hledá ikonu "odesláno" status dot
        // Hledáme ikonu specificky pro řádek tohoto kontaktu, pokud je to možné,
        // nebo obecně, pokud je struktura jednoduchá.
        // Dashboard používá: <i class="bi bi-circle-fill status-sent-dot ms-2" ...>

        // Nejprve najdeme zaškrtávací políčko pro tento kontakt, pak hledáme ikonu v jeho blízkosti nebo řádku
        // Používáme xpath pro hledání ikony uvnitř stejného wrapperu, kde je zaškrtávací políčko se specifickou hodnotou

        // Zjednodušená kontrola: zajistíme, že existuje alespoň jedna ikona "odesláno" (protože jsme ji právě vytvořili)
        // a ideálně zkontrolujeme, že je blízko našemu kontaktu.

        // Přesnější: Najdeme zaškrtávací políčko, vyhledáme jeho wrapper, pak hledáme ikonu.
        $crawler = $client->getCrawler();
        $checkbox = $crawler->filter('input[name="contacts[]"][value="' . $idStr . '"]');

        self::assertCount(1, $checkbox, 'Contact checkbox should exist');

        // Zaškrtávací políčko je uvnitř .contact-item-wrapper spolu s ikonou
        $wrapper = $checkbox->closest('.contact-item-wrapper');
        self::assertNotNull($wrapper);
        $icon = $wrapper->filter('.status-sent-dot');

        self::assertCount(1, $icon, 'Sent status dot should be present for greeted contact');
    }

    /**
     * Testuje, že import kontaktů odmítne neplatný CSRF token.
     * Zkontroluje, že uživatel je přesměrován zpět na dashboard a zobrazí se chybová zpráva.
     */
    public function testImportRejectsInvalidCsrfToken(): void
    {
        $client = static::createClient();

        // Simuluje odeslání formuláře s neplatným CSRF tokenem
        $client->request('POST', '/ru/greeting/import', [
            'greeting_import' => [
                'emails' => 'test@example.com',
                'language' => 'en',
                'registrationDate' => '2025-01-01',
                '_token' => 'invalid_token_value',
            ],
        ]);

        // Očekáváme přesměrování zpět na dashboard kvůli chybě validace (CSRF je součástí validace)
        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        // Očekáváme obecnou chybovou zprávu validace
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * Testuje, že odeslání pozdravu odmítne neplatný CSRF token.
     * Zkontroluje, že uživatel je přesměrován zpět na dashboard a zobrazí se chybová zpráva.
     */
    public function testSendRejectsInvalidCsrfToken(): void
    {
        $client = static::createClient();
        $id = $this->createContact('csrf_test_' . uniqid('', true) . '@example.com');

        $client->request('POST', '/ru/greeting/send', [
            'contacts' => [$id],
            'subject' => 'Subject',
            'body' => 'Body',
            '_token' => 'invalid_token_value',
        ]);

        // Očekáváme Access Denied (403) nebo přesměrování s chybou, v závislosti na implementaci.
        // Standardní přístup je často 422 nebo 400 pro neplatný CSRF.
        // Nicméně, dodržujeme vzor kontroleru, který by mohl přesměrovat s flash zprávou.
        // Implementujeme kontroler tak, aby vrátil 422 nebo 400 pro porušení bezpečnosti při manuálním kontrole,
        // nebo přesměroval s chybovou flash zprávou.
        // Pro tento test předpokládáme, že implementujeme přesměrování s chybovou flash zprávou pro konzistenci.

        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        self::assertSelectorExists('.alert-danger');
        // Můžeme zkontrolovat specifickou zprávu, pokud ji přidáme, např. "Invalid CSRF token"
    }
}
