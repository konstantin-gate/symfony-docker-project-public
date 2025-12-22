<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Controller;

use App\Greeting\Entity\GreetingContact;
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

        $form = $crawler->filter('button[name="greeting_import[import]"]')->form();
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

        $form = $crawler->filter('button[name="greeting_import[import]"]')->form();

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

        $form = $crawler->filter('button[name="greeting_import[import]"]')->form();

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

        $form = $crawler->filter('button[name="greeting_import[import]"]')->form();

        $email = 'combined_dup_' . uniqid('', true) . '@example.com';

        // XML with email
        $xmlContent = <<<XML
<contacts>
    <contact>
        <email>{$email}</email>
    </contact>
</contacts>
XML;
        $tempFile = sys_get_temp_dir() . '/test_combined_' . uniqid('', true) . '.xml';
        file_put_contents($tempFile, $xmlContent);

        try {
            $form['greeting_import[language]'] = 'en';
            $form['greeting_import[xmlFile]'] = $tempFile;
            $form['greeting_import[emails]'] = $email; // Same email in text

            $client->submit($form);

            self::assertResponseRedirects('/ru/greeting/dashboard');
            $client->followRedirect();

            self::assertSelectorExists('.alert-success');
            // Expect 1 unique import despite being in both sources
            self::assertSelectorTextContains('.alert-success', '1');

            // Verify contact persistence
            /** @var EntityManagerInterface $em */
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $contacts = $em->getRepository(GreetingContact::class)->findBy(['email' => $email]);
            self::assertCount(1, $contacts);

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testImportInvalidXmlFileFails(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/ru/greeting/dashboard');

        $form = $crawler->filter('button[name="greeting_import[import]"]')->form();

        // Invalid XML content (missing closing tag)
        $xmlContent = "<contacts>\n    <contact>\n        <email>broken@example.com</email>\n    </contact>";
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

        // Verify the contact checkbox exists
        self::assertSelectorExists('input[name="contacts[]"][value="' . $id . '"]');

        // Bypass DomCrawler form validation which fails on large contact lists
        $client->request('POST', '/ru/greeting/send', [
            'contacts' => [$id],
            'subject' => 'Test Subject',
            'body' => 'Test Body',
        ]);

        self::assertResponseRedirects('/ru/greeting/dashboard');
        $client->followRedirect();

        self::assertSelectorExists('.alert-success');
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
}
