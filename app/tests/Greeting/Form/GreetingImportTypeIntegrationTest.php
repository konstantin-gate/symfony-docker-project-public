<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Form;

use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Form\GreetingImportType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GreetingImportTypeIntegrationTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;
    private ValidatorInterface $validator;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->formFactory = $container->get(FormFactoryInterface::class);
        $this->validator = $container->get(ValidatorInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Spustíme transakci pro každý test
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Po každém testu provedeme rollback transakce
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    /**
     * Integrační test: vytvoření formuláře přes kontejner a kontrola konfigurace.
     */
    public function testFormCreationFromContainer(): void
    {
        $form = $this->formFactory->create(GreetingImportType::class);

        $this->assertInstanceOf(FormInterface::class, $form);

        // Kontrola konfigurace formuláře
        $config = $form->getConfig();
        $this->assertEquals('greeting', $config->getOption('translation_domain'));

        // Kontrola přítomnosti polí
        $this->assertTrue($form->has('emails'));
        $this->assertTrue($form->has('registrationDate'));
        $this->assertTrue($form->has('language'));
        $this->assertTrue($form->has('import'));
    }

    /**
     * Integrační test: odeslání platných dat s kontrolou validace přes kontejner.
     */
    public function testSubmitValidDataWithContainerValidation(): void
    {
        $form = $this->formFactory->create(GreetingImportType::class);

        $form->submit([
            'emails' => "test1@example.com\ntest2@example.com",
            'registrationDate' => (new \DateTimeImmutable())->format('Y-m-d'),
            'language' => GreetingLanguage::Czech->value,
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        // Kontrola dat formuláře
        $data = $form->getData();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('emails', $data);
        $this->assertArrayHasKey('registrationDate', $data);
        $this->assertArrayHasKey('language', $data);

        // Kontrola typů dat
        $this->assertIsString($data['emails']);
        $this->assertInstanceOf(\DateTimeInterface::class, $data['registrationDate']);
        $this->assertInstanceOf(GreetingLanguage::class, $data['language']);
    }

    /**
     * Integrační test: kontrola validace přes samostatný validator kontejneru.
     */
    public function testValidationWithContainerValidator(): void
    {
        $form = $this->formFactory->create(GreetingImportType::class);
        $form->submit([
            'emails' => "test1@example.com\ninvalid_email",
            'registrationDate' => (new \DateTimeImmutable())->format('Y-m-d'),
            'language' => GreetingLanguage::Czech->value,
        ]);

        // Kontrola přes validator kontejneru
        $violations = $this->validator->validate($form);

        $this->assertGreaterThan(0, $violations->count());

        // Hledání chyb v poli emails
        $emailErrors = [];

        foreach ($violations as $violation) {
            if (str_contains($violation->getPropertyPath(), 'emails')) {
                $emailErrors[] = $violation->getMessage();
            }
        }

        $this->assertNotEmpty($emailErrors, 'Měly by existovat chyby validace e-mailu');
    }

    /**
     * Integrační test: kontrola překladů a popisků.
     */
    public function testTranslationsAndLabels(): void
    {
        $form = $this->formFactory->create(GreetingImportType::class);

        // Kontrola atributů pole emails
        $emailsAttr = $form->get('emails')->getConfig()->getOption('attr');
        $this->assertArrayHasKey('rows', $emailsAttr);
        $this->assertArrayHasKey('placeholder', $emailsAttr);
        $this->assertEquals(6, $emailsAttr['rows']);
        $this->assertEquals('import.emails_placeholder', $emailsAttr['placeholder']);

        // Kontrola popisků přes konfiguraci
        $emailsConfig = $form->get('emails')->getConfig();
        $this->assertEquals('import.emails_label', $emailsConfig->getOption('label'));

        $dateConfig = $form->get('registrationDate')->getConfig();
        $this->assertEquals('import.date_label', $dateConfig->getOption('label'));

        $languageConfig = $form->get('language')->getConfig();
        $this->assertEquals('import.language_label', $languageConfig->getOption('label'));

        $submitConfig = $form->get('import')->getConfig();
        $this->assertEquals('import.submit', $submitConfig->getOption('label'));
    }

    /**
     * Integrační test: kontrola práce s EnumType z kontejneru.
     */
    public function testEnumTypeIntegration(): void
    {
        $form = $this->formFactory->create(GreetingImportType::class);

        // Kontrola konfigurace EnumType
        $languageConfig = $form->get('language')->getConfig();
        $this->assertEquals(GreetingLanguage::class, $languageConfig->getOption('class'));

        // Kontrola callbacku choice_label
        $choiceLabel = $languageConfig->getOption('choice_label');
        $this->assertIsCallable($choiceLabel);

        // Kontrola fungování callbacku
        foreach (GreetingLanguage::cases() as $language) {
            $label = $choiceLabel($language);
            $this->assertEquals('language.' . $language->name, $label);
        }
    }

    /**
     * Integrační test: kontrola výchozí hodnoty data.
     */
    public function testDefaultDateValue(): void
    {
        $form = $this->formFactory->create(GreetingImportType::class);

        // Kontrola, že je nastavena výchozí hodnota
        $dateConfig = $form->get('registrationDate')->getConfig();
        $this->assertTrue($dateConfig->hasOption('data'));
        $defaultDate = $dateConfig->getOption('data');

        $this->assertInstanceOf(\DateTimeImmutable::class, $defaultDate);

        // Datum by mělo být přibližně nyní (plus-minus 1 sekunda)
        $now = new \DateTimeImmutable();
        $diff = abs($now->getTimestamp() - $defaultDate->getTimestamp());
        $this->assertLessThanOrEqual(1, $diff);
    }

    /**
     * Integrační test: kontrola zpracování prázdných dat s reálným kontejnerem.
     */
    public function testEmptyDataHandling(): void
    {
        $form = $this->formFactory->create(GreetingImportType::class);

        // Toto vyvolá TypeError v callback-validatoru v předchozí verzi
        $form->submit([]);

        $this->assertTrue($form->isSynchronized());
        // Form might be invalid due to other fields (registrationDate), 
        // but it should not throw TypeError.
    }

    /**
     * Integrační test: kontrola hromadného odeslání dat s reálným kontejnerem.
     */
    public function testBulkDataSubmission(): void
    {
        $form = $this->formFactory->create(GreetingImportType::class);

        // Generování 100 e-mailů
        $emails = [];
        for ($i = 1; $i <= 100; ++$i) {
            $emails[] = "user$i@example.com";
        }
        $emailString = implode("\n", $emails);

        $form->submit([
            'emails' => $emailString,
            'registrationDate' => '2024-12-25',
            'language' => GreetingLanguage::English->value,
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        // Kontrola, že všechny e-maily byly uloženy
        $data = $form->getData();
        $submittedEmails = preg_split('/[\s,;]+/', $data['emails'], -1, \PREG_SPLIT_NO_EMPTY);

        $this->assertCount(100, (array) $submittedEmails);
    }

    /**
     * Integrační test: kontrola zpracování různých formátů data.
     */
    public function testVariousDateFormats(): void
    {
        $testDates = [
            '2024-12-25', // Standardní formát
            '2024-01-01', // Nový rok
            '2024-02-29', // Přestupný rok (2024 je přestupný)
        ];

        foreach ($testDates as $dateString) {
            // Vytvoříme NOVÝ formulář pro každou iteraci
            $form = $this->formFactory->create(GreetingImportType::class);

            $form->submit([
                'emails' => 'test@example.com',
                'registrationDate' => $dateString,
                'language' => GreetingLanguage::Czech->value,
            ]);

            $this->assertTrue(
                $form->isSynchronized(),
                "Datum $dateString by mělo být synchronizováno"
            );

            $this->assertTrue(
                $form->isValid(),
                "Datum $dateString by mělo být platné"
            );

            // Kontrola převodu data
            $date = $form->get('registrationDate')->getData();
            $this->assertInstanceOf(\DateTimeInterface::class, $date);
            $this->assertEquals($dateString, $date->format('Y-m-d'));
        }
    }

    /**
     * Integrační test: kontrola neplatných dat přes kontejner.
     */
    public function testInvalidDatesThroughContainer(): void
    {
        $invalidDates = [
            '2024-13-01', // Neplatný měsíc
            '2024-02-30', // Neplatný den pro únor (2024 je přestupný, ale 30. února neexistuje)
            'not-a-date', // Vůbec není datum
            '2024-04-31', // Duben má jen 30 dní
            'invalid',    // Zcela neplatný formát
        ];

        foreach ($invalidDates as $dateString) {
            // Nový formulář pro každou iteraci
            $form = $this->formFactory->create(GreetingImportType::class);

            $form->submit([
                'emails' => 'test@example.com',
                'registrationDate' => $dateString,
                'language' => GreetingLanguage::Czech->value,
            ]);

            $this->assertTrue(
                $form->isSynchronized(),
                "Formulář by měl být synchronizován i s neplatným datem $dateString"
            );

            $this->assertFalse(
                $form->isValid(),
                "Formulář by měl být neplatný při zadání data: $dateString"
            );

            // Kontrolujeme přítomnost chyb právě na poli registrationDate
            $dateField = $form->get('registrationDate');
            $errors = $dateField->getErrors();

            $this->assertGreaterThan(
                0,
                $errors->count(),
                "Pole registrationDate by mělo obsahovat alespoň jednu chybu validace při neplatném datu: $dateString"
            );
        }
    }

    /**
     * Integrační test: kontrola všech dostupných jazyků přes kontejner.
     */
    public function testAllAvailableLanguages(): void
    {
        foreach (GreetingLanguage::cases() as $language) {
            // Vytvoříme NOVÝ formulář pro každý jazyk
            $form = $this->formFactory->create(GreetingImportType::class);

            $form->submit([
                'emails' => 'test@example.com',
                'registrationDate' => '2024-12-25',  // Platné datum, aby nebyly chyby validace
                'language' => $language->value,
            ]);

            $this->assertTrue(
                $form->isSynchronized(),
                "Jazyk $language->value by měl být synchronizován"
            );

            $this->assertTrue(
                $form->isValid(),
                "Jazyk $language->value by měl být platný"
            );

            // Kontrola, že jazyk byl správně převeden na objekt Enum
            $submittedLanguage = $form->get('language')->getData();
            $this->assertInstanceOf(GreetingLanguage::class, $submittedLanguage);
            $this->assertEquals($language, $submittedLanguage);
        }
    }

    /**
     * Integrační test: kontrola zpracování různých oddělovačů e-mailů přes kontejner.
     */
    public function testVariousEmailSeparators(): void
    {
        $testCases = [
            [
                'input' => "test1@example.com\ntest2@example.com\ntest3@example.com",
                'expected_count' => 3,
            ],
            [
                'input' => 'test1@example.com,test2@example.com,test3@example.com',
                'expected_count' => 3,
            ],
            [
                'input' => 'test1@example.com;test2@example.com;test3@example.com',
                'expected_count' => 3,
            ],
            [
                'input' => 'test1@example.com test2@example.com test3@example.com',
                'expected_count' => 3,
            ],
            [
                'input' => "test1@example.com\ttest2@example.com\ttest3@example.com",
                'expected_count' => 3,
            ],
        ];

        foreach ($testCases as $testCase) {
            // Vytvoříme NOVÝ formulář pro každý testovací případ
            $form = $this->formFactory->create(GreetingImportType::class);

            $form->submit([
                'emails' => $testCase['input'],
                'registrationDate' => '2024-12-25',
                'language' => GreetingLanguage::Czech->value,
            ]);

            $this->assertTrue($form->isSynchronized());
            $this->assertTrue($form->isValid());

            // Kontrola počtu e-mailů po normalizaci
            $data = $form->getData();
            $emails = preg_split('/[\s,;]+/', $data['emails'], -1, \PREG_SPLIT_NO_EMPTY);
            $this->assertCount(
                $testCase['expected_count'],
                (array) $emails,
                "Vstup s oddělovači by měl být správně rozdělen na {$testCase['expected_count']} e-mailů"
            );
        }
    }

    /**
     * Integrační test: kontrola zpracování prázdných řetězců mezi oddělovači.
     */
    public function testEmptyStringsBetweenSeparators(): void
    {
        // Řetězec s vícenásobnými oddělovači za sebou
        $testInputs = [
            'test1@example.com,,,test2@example.com',
            'test1@example.com;;;test2@example.com',
            'test1@example.com   test2@example.com',
            "test1@example.com\n\n\ntest2@example.com",
        ];

        foreach ($testInputs as $input) {
            // Vytvoříme NOVÝ formulář pro každý testovací případ
            $form = $this->formFactory->create(GreetingImportType::class);

            $form->submit([
                'emails' => $input,
                'registrationDate' => '2024-12-25',
                'language' => GreetingLanguage::Czech->value,
            ]);

            $this->assertTrue($form->isSynchronized());
            $this->assertTrue($form->isValid());

            // Měly by být přesně 2 e-maily po vyčištění prázdných prvků
            $data = $form->getData();
            $emails = preg_split('/[\s,;]+/', $data['emails'], -1, \PREG_SPLIT_NO_EMPTY);
            $this->assertCount(
                2,
                (array) $emails,
                "Vstup '$input' by měl být vyčištěn od prázdných prvků a obsahovat přesně 2 e-maily"
            );
        }
    }

    /**
     * Integrační test: kontrola validace pomocí skutečné validační služby.
     */
    public function testConstraintValidationWithRealValidator(): void
    {
        // Vytvoříme formulář
        $form = $this->formFactory->create(GreetingImportType::class);

        // Získáme konfiguraci pole emails
        $emailsConfig = $form->get('emails')->getConfig();

        // Získáme constraints z konfigurace
        $constraints = $emailsConfig->getOption('constraints');

        // Kontrola, že jsou constraints nastaveny
        $this->assertIsArray($constraints);
        $this->assertCount(1, $constraints); // Callback

        // Kontrola typů constraints
        $constraintTypes = array_map('get_class', $constraints);
        // $this->assertContains(NotBlank::class, $constraintTypes); // Removed NotBlank
        $this->assertContains(Callback::class, $constraintTypes);
    }

    /**
     * Integrační test: kontrola konfigurace tlačítka odeslání.
     */
    public function testSubmitButtonConfiguration(): void
    {
        $form = $this->formFactory->create(GreetingImportType::class);

        $submitConfig = $form->get('import')->getConfig();

        // Kontrola konfigurace tlačítka
        $this->assertEquals('import.submit', $submitConfig->getOption('label'));

        $attr = $submitConfig->getOption('attr');
        $this->assertArrayHasKey('class', $attr);
        $this->assertEquals('btn btn-primary w-100', $attr['class']);
    }

    /**
     * Integrační test: kontrola fungování formuláře v různých locale.
     */
    public function testFormWithDifferentLocales(): void
    {
        // Testujeme s různými locale (simulujeme změnu request contextu)
        $locales = ['cs', 'en', 'ru'];

        foreach ($locales as $locale) {
            // Vytvoříme formulář v kontextu locale
            // V reálné aplikaci by to bylo provedeno přes RequestContext
            $form = $this->formFactory->create(GreetingImportType::class);

            $form->submit([
                'emails' => 'test@example.com',
                'registrationDate' => '2024-12-25',
                'language' => GreetingLanguage::from($locale)->value,
            ]);

            $this->assertTrue($form->isSynchronized());
            $this->assertTrue($form->isValid());

            // Kontrola, že jazyk byl správně nastaven
            $submittedLanguage = $form->get('language')->getData();
            $this->assertEquals(GreetingLanguage::from($locale), $submittedLanguage);
        }
    }
}
