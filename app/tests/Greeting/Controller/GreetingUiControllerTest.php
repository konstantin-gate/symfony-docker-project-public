<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Controller;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Entity\GreetingLog;
use App\Greeting\Enum\GreetingLanguage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GreetingUiControllerTest extends WebTestCase
{
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

    public function testDashboardIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ru/greeting/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="greeting_import"]');
    }

    public function testImportRedirectsAndShowsFlashOnInvalidData(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $form = $crawler->selectButton('greeting_import[import]')->form();
        // Submit empty form
        $client->submit($form);

        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        // Check for error flash message (alert-danger)
        self::assertSelectorExists('.alert-danger');
    }

    public function testImportTextContentSuccess(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $form = $crawler->selectButton('greeting_import[import]')->form();

        $form['greeting_import[language]'] = 'en';
        $form['greeting_import[emails]'] = 'test@example.com';

        $client->submit($form);

        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        // Check for success flash message (alert-success)
        self::assertSelectorExists('.alert-success');
    }

    public function testImportXmlFileSuccess(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $form = $crawler->selectButton('greeting_import[import]')->form();

        $uniqueEmail = 'xml_test_' . uniqid('', true) . '@example.com';
        // Create temporary XML file
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

            // Verify contact persistence
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

    public function testImportCombinedXmlAndTextWithDuplicates(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $form = $crawler->selectButton('greeting_import[import]')->form();

        $duplicateEmail = 'shared_' . uniqid('', true) . '@example.com';
        $xmlOnlyEmail = 'xml_only_' . uniqid('', true) . '@example.com';
        $textOnlyEmail = 'text_only_' . uniqid('', true) . '@example.com';

        // XML with shared and unique email
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
            // Expect 3 unique imports: 1 shared + 1 XML only + 1 text only
            self::assertSelectorTextContains('.alert-success', '3');

            // Verify contact persistence
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

    public function testImportInvalidXmlFileFails(): void
    {
        $client = static::createClient();

        // Get initial count to ensure no changes are made
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repo = $em->getRepository(GreetingContact::class);
        $initialCount = $repo->count([]);

        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $form = $crawler->selectButton('greeting_import[import]')->form();

        // Invalid XML content (missing closing tag for email)
        $xmlContent = '<contacts><email>broken@example.com</contacts>';
        $tempFile = sys_get_temp_dir() . '/test_invalid_' . uniqid('', true) . '.xml';
        file_put_contents($tempFile, $xmlContent);

        try {
            $form['greeting_import[language]'] = 'en';
            $form['greeting_import[xmlFile]'] = $tempFile;

            $client->submit($form);

            self::assertResponseRedirects('/ru/greeting/dashboard');
            $client->followRedirect();

            // Check for error flash message (alert-danger)
            self::assertSelectorExists('.alert-danger');

            // Verify DB count hasn't changed
            self::assertSame($initialCount, $repo->count([]));
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSendGreetingSuccessfully(): void
    {
        $client = static::createClient();
        $email = 'send_test_' . uniqid('', true) . '@example.com';
        $id = $this->createContact($email);

        $crawler = $client->request('GET', '/ru/greeting/dashboard');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        // Verify the contact checkbox exists
        self::assertSelectorExists('input[name="contacts[]"][value="' . $id . '"]');

        // Bypass DomCrawler form validation which fails on large contact lists
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

    public function testSendToMultipleContactsWithDifferentLanguages(): void
    {
        $client = static::createClient();
        $idEn = $this->createContact('en_multi_' . uniqid('', true) . '@example.com', 'en');
        $idRu = $this->createContact('ru_multi_' . uniqid('', true) . '@example.com', 'ru');

        // Start session
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
        // Expect success message indicating 2 emails were queued/sent
        self::assertSelectorTextContains('.alert-success', '2');
    }

    public function testSendWithNoContactsShowsError(): void
    {
        $client = static::createClient();
        // Start session
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

        // The controller uses 'error' flash which results in alert-danger
        self::assertSelectorExists('.alert-danger');
    }

    /**
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

        // Follow "reload" by requesting the dashboard and check for warning flash
        $client->request('GET', '/ru/greeting/dashboard');
        self::assertSelectorExists('.alert-warning');
    }

    /**
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

        // Follow "reload" by requesting the dashboard and check for warning flash
        $client->request('GET', '/ru/greeting/dashboard');
        self::assertSelectorExists('.alert-warning');
    }

    public function testGenerateTestEmailsInDevReturnsEmails(): void
    {
        // By default, static::createClient() uses 'test' environment,
        // which is NOT 'prod', so the check in controller allows it.
        $client = static::createClient();
        $client->request('GET', '/greeting/generate-test-emails');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('@', $content);
        self::assertCount(10, explode(' ', trim($content)));
    }

    public function testImportInEnglishLocaleShowsEnglishFlash(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/greeting/dashboard');

        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('greeting_import[import]')->form();
        // Submit empty form to trigger validation error
        $client->submit($form);

        self::assertResponseRedirects('/en/greeting/dashboard');
        $client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Please provide either a list of emails or an XML file');
    }

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

    public function testDashboardShowsSentStatusMarkerForGreetedContacts(): void
    {
        $client = static::createClient();
        $email = 'greeted_' . uniqid('', true) . '@example.com';
        $idStr = $this->createContact($email);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $contact = $em->getRepository(GreetingContact::class)->find($idStr);
        self::assertNotNull($contact);

        // Create a log entry indicating a greeting was sent
        $log = new GreetingLog($contact, (int) date('Y'));
        $em->persist($log);
        $em->flush();

        $client->request('GET', '/ru/greeting/dashboard');

        // Check for the "sent" status dot icon
        // We look for the dot specifically associated with this contact row/wrapper if possible,
        // or just generally if the structure is simple.
        // The dashboard uses: <i class="bi bi-circle-fill status-sent-dot ms-2" ...>

        // Let's find the checkbox for this contact first, then look for the icon in its vicinity or row
        // Using xpath to find the icon inside the same wrapper where the checkbox with specific value is

        // Simplified check: ensure at least one sent marker exists (since we just made one)
        // and ideally check it is near our contact.

        // More precise: Find the checkbox, traverse up to the wrapper, then look for the icon.
        $crawler = $client->getCrawler();
        $checkbox = $crawler->filter('input[name="contacts[]"][value="' . $idStr . '"]');

        self::assertCount(1, $checkbox, 'Contact checkbox should exist');

        // The checkbox is inside .contact-item-wrapper along with the icon
        $wrapper = $checkbox->closest('.contact-item-wrapper');
        self::assertNotNull($wrapper);
        $icon = $wrapper->filter('.status-sent-dot');

        self::assertCount(1, $icon, 'Sent status dot should be present for greeted contact');
    }

    public function testImportRejectsInvalidCsrfToken(): void
    {
        $client = static::createClient();

        // Simulating a form submission with an invalid CSRF token
        $client->request('POST', '/ru/greeting/import', [
            'greeting_import' => [
                'emails' => 'test@example.com',
                'language' => 'en',
                'registrationDate' => '2025-01-01',
                '_token' => 'invalid_token_value',
            ],
        ]);

        // Expect redirect back to dashboard due to validation error (CSRF is part of validation)
        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        // Expect generic validation error message
        self::assertSelectorExists('.alert-danger');
    }

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

        // Expect Access Denied (403) or Redirect with error, depending on implementation.
        // Standard approach is often 403 or 422 for invalid CSRF.
        // However, sticking to the controller pattern, it might redirect with flash.
        // Let's implement the controller to return 422 or 400 for security violation on manual check,
        // or redirect with error flash.
        // For this test, let's assume we will implement a redirect with error flash for consistency.

        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        self::assertSelectorExists('.alert-danger');
        // We can check for a specific message if we add one, e.g. "Invalid CSRF token"
    }
}
