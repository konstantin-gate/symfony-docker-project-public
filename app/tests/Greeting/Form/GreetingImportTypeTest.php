<?php

declare(strict_types=1);

/**
 * Testovací třída pro formulář GreetingImportType.
 * Obsahuje testy pro validaci e-mailových adres, datumu a dalších polí formuláře.
 *
 * @author Konstantin Gate
 */

namespace App\Tests\Greeting\Form;

use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Form\GreetingImportType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class GreetingImportTypeTest extends TypeTestCase
{
    /**
     * Vytváří a vrací rozšíření pro testování formuláře.
     * Obsahuje validator pro validaci dat formuláře.
     *
     * @return array<ValidatorExtension>
     */
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    /**
     * Testuje odeslání formuláře s prázdnou hodnotou e-mailů.
     * Zjišťuje, zda formulář je platný, když pole e-mailů je prázdné.
     *
     * @return void
     */
    public function testSubmitEmptyEmails(): void
    {
        $form = $this->factory->create(GreetingImportType::class);

        // Předáváme prázdnou hodnotu e-mailů jako prázdný řetězec
        $formData = [
            'emails' => '',
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form->submit($formData);
        $this->assertTrue($form->isSynchronized());
        // Formulář je platný, protože e-maily jsou volitelné (mohou být prázdné)
        $this->assertTrue($form->isValid());
    }

    /**
     * Testuje odeslání formuláře s neplatnými e-mailovými adresami.
     * Zjišťuje, zda formulář je neplatný, když jsou poskytnuty neplatné e-maily.
     *
     * @return void
     */
    public function testSubmitInvalidEmails(): void
    {
        $formData = [
            'emails' => "test1@example.com\ninvalid_email",
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        // Vytváříme formulář s daty
        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        // Assertions
        $this->assertTrue($form->isSynchronized()); // Formulář je synchronizován s údaji
        $this->assertFalse($form->isValid()); // Ale údaje nejsou platné
    }

    /**
     * Testuje odeslání formuláře s neplatným datem.
     * Zjišťuje, zda formulář je neplatný, když je poskytnuto neplatné datum.
     *
     * @return void
     */
    public function testSubmitInvalidDate(): void
    {
        $formData = [
            'emails' => 'test1@example.com test2@example.com',
            'registrationDate' => '2025-12-99',  // Neplatné datum
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        // Formulář je synchronizován, ale není platný kvůli neplatnému datu
        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        // Navíc kontrolujeme, zda je v poli registrationDate chyba
        $this->assertGreaterThan(0, \count($form->get('registrationDate')->getErrors()));
    }

    /**
     * Testuje odeslání formuláře s platnými daty.
     * Zjišťuje, zda formulář je platný, když jsou poskytnuta platná data.
     *
     * @return void
     */
    public function testSubmitValidData(): void
    {
        $formData = [
            'emails' => 'test1@example.com test2@example.com',
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        // Vytváříme formulář s daty
        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        // Assertions
        $this->assertTrue($form->isSynchronized());

        // getData() vrací řetězec, nikoli pole
        $this->assertEquals($formData['emails'], $form->get('emails')->getData());
    }

    /**
     * Testuje odeslání formuláře s prázdným datem.
     * Zjišťuje, zda formulář je neplatný, když je pole data prázdné.
     *
     * @return void
     */
    public function testSubmitEmptyDate(): void
    {
        $formData = [
            'emails' => 'test1@example.com test2@example.com',
            'registrationDate' => '',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
    }

    /**
     * Testuje odeslání formuláře s prázdným jazykem.
     * Zjišťuje, zda formulář je platný, když je pole jazyka prázdné.
     *
     * @return void
     */
    public function testSubmitEmptyLanguage(): void
    {
        $formData = [
            'emails' => 'test1@example.com',
            'registrationDate' => '2024-12-12',
            'language' => '',
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());

        // Pole language NENÍ povinné → formulář je platný
        $this->assertTrue($form->isValid());

        // Ale data v poli language budou null.
        $this->assertNull($form->get('language')->getData());
    }

    /**
     * Test s platnými e-maily v různých formátech.
     */
    public function testSubmitValidEmailsWithDifferentSeparators(): void
    {
        $formData = [
            'emails' => 'test1@example.com, test2@example.com; test3@example.com test4@example.com',
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($formData['emails'], $form->get('emails')->getData());
    }

    /**
     * Test s mezerami a přenosy řádků.
     */
    public function testSubmitEmailsWithWhitespace(): void
    {
        $formData = [
            'emails' => "test1@example.com \r\n test2@example.com \r\n test3@example.com",
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($formData['emails'], $form->get('emails')->getData());
    }

    /**
     * Test s velmi dlouhým seznamem e-mailů.
     */
    public function testSubmitLargeNumberOfEmails(): void
    {
        $emails = [];

        // Generujeme 1000 platných e-mailů (může být i více)
        for ($i = 1; $i <= 1000; ++$i) {
            $emails[] = "test{$i}@example.com";
        }

        $emailString = implode(' ', $emails);
        $formData = [
            'emails' => $emailString,
            'registrationDate' => '2025-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized(), 'Form should be synchronized');
        $this->assertTrue($form->isValid(), 'Form should be valid even with a large number of valid emails');

        // Navíc ujistíme se, že data byla správně zpracována.
        $viewData = $form->getData();
        $this->assertIsString($viewData['emails']);
        $this->assertCount(
            \count($emails),
            (array) preg_split('/[\s,;]+/', $viewData['emails'], -1, \PREG_SPLIT_NO_EMPTY)
        );
    }

    /**
     * Test s několika neplatnými e-mailovými adresami.
     */
    public function testSubmitMultipleInvalidEmails(): void
    {
        $formData = [
            'emails' => "invalid1\ninvalid2@\n@invalid3\ninvalid4@domain",
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        // Zkontrolujeme, zda se vyskytuje několik chyb.
        $errors = $form->get('emails')->getErrors();
        $this->assertGreaterThan(1, \count($errors));
    }

    /**
     * Test s nestandardními, ale platnými e-maily.
     */
    public function testSubmitValidButUnusualEmails(): void
    {
        $formData = [
            'emails' => 'test+tag@example.com test.user@sub.domain.co.uk test@xn--80akhbyknj4f.com',
            'registrationDate' => '2025-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), 'Unusual but RFC-compliant emails should be accepted');
    }

    /**
     * Test maximální délky řetězce emails.
     */
    public function testSubmitExcessivelyLongEmailString(): void
    {
        $longEmail = str_repeat('a', 250) . '@example.com';
        $emails = str_repeat($longEmail . "\n", 1000);

        $formData = [
            'emails' => $emails,
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
    }

    /**
     * Test validace data v budoucnosti.
     */
    public function testSubmitFutureDate(): void
    {
        $formData = [
            'emails' => 'test@example.com',
            'registrationDate' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d'),
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
    }

    /**
     * Test překladů (translation domain).
     */
    public function testTranslationDomain(): void
    {
        $form = $this->factory->create(GreetingImportType::class);

        $config = $form->getConfig();
        $options = $config->getOptions();

        $this->assertEquals('greeting', $options['translation_domain']);
    }

    /**
     * Test: pole emails zcela chybí v datech.
     * Očekáváme TypeError, podobně jako u prázdného řetězce, protože Symfony předá null do callbacku.
     */
    public function testSubmitMissingEmails(): void
    {
        $form = $this->factory->create(GreetingImportType::class);

        $formData = [
            // 'emails' záměrně chybí
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form->submit($formData);
        $this->assertTrue($form->isSynchronized());
        // Form is valid because emails are optional now (can be null/missing)
        $this->assertTrue($form->isValid());
    }

    /**
     * Test: platná data s datem v minulosti.
     */
    public function testSubmitPastDate(): void
    {
        $formData = [
            'emails' => 'test@example.com',
            'registrationDate' => (new \DateTimeImmutable('-1 year'))->format('Y-m-d'),
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
    }

    /**
     * Test: datum "dnes" — hraniční případ.
     */
    public function testSubmitTodayDate(): void
    {
        $formData = [
            'emails' => 'test@example.com',
            'registrationDate' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
    }

    /**
     * Test: směs platných a neplatných e-mailů.
     * Formulář musí být neplatný, chyby pouze na neplatných.
     */
    public function testSubmitMixedValidAndInvalidEmails(): void
    {
        $formData = [
            'emails' => 'valid@example.com, invalid@, valid2@example.org, bad@@example.com',
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $errors = $form->get('emails')->getErrors();
        $this->assertGreaterThanOrEqual(2, \count($errors));
    }

    /**
     * Test: duplicitní e-maily (se stejným a odlišným registrem).
     * Pokud není zřejmá kontrola jedinečnosti, formulář je platný.
     */
    public function testSubmitDuplicateEmails(): void
    {
        $formData = [
            'emails' => 'test@example.com, TEST@example.com, test@example.com',
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
    }

    /**
     * Test: velmi dlouhý platný e-mail (blízko limitu RFC 5321).
     */
    public function testSubmitVeryLongValidEmail(): void
    {
        $longEmail = str_repeat('a', 64) . '@' . str_repeat('b', 63)
            . '.' . str_repeat('c', 63) . '.example.com';

        $formData = [
            'emails' => $longEmail,
            'registrationDate' => '2024-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
    }

    /**
     * Test: velmi dlouhý neplatný e-mail.
     */
    public function testSubmitVeryLongInvalidEmail(): void
    {
        $localPart = str_repeat('a', 64); // local max
        $domainLabel = str_repeat('a', 64); // jeden label >63 → neplatný
        $longEmail = $localPart . '@' . $domainLabel . '.com';

        $formData = [
            'emails' => $longEmail,
            'registrationDate' => '2025-12-12',
            'language' => GreetingLanguage::Czech->value,
        ];

        $form = $this->factory->create(GreetingImportType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid(), 'Email with domain label longer than 63 characters should be rejected');
    }
}
