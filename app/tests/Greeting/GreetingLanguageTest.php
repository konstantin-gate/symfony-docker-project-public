<?php

declare(strict_types=1);

namespace App\Tests\Greeting;

use App\Greeting\Enum\GreetingLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Testovací třída pro enum GreetingLanguage.
 * Obsahuje testy pro metody getSubject() a getTemplatePath().
 */
class GreetingLanguageTest extends TestCase
{
    /**
     * Testuje metodu getSubject() pro všechny jazyky.
     * Zjišťuje, zda metoda vrací správný předmět e-mailu pro daný jazyk.
     */
    #[DataProvider('provideLanguageSubjects')]
    public function testGetSubject(GreetingLanguage $language, string $expectedSubject): void
    {
        $this->assertSame($expectedSubject, $language->getSubject());
    }

    /**
     * Poskytuje testovací data pro metodu testGetSubject().
     * Obsahuje páry jazyk-předmět pro všechny podporované jazyky.
     *
     * @return array<array<GreetingLanguage|string>>
     */
    public static function provideLanguageSubjects(): array
    {
        return [
            [GreetingLanguage::Czech, 'Veselé Vánoce a šťastný nový rok!'],
            [GreetingLanguage::English, 'Merry Christmas and Happy New Year!'],
            [GreetingLanguage::Russian, 'С Рождеством и Новым годом!'],
        ];
    }

    /**
     * Testuje metodu getTemplatePath() pro všechny jazyky.
     * Zjišťuje, zda metoda vrací správnou cestu k šabloně pro daný jazyk.
     */
    #[DataProvider('provideLanguageTemplatePaths')]
    public function testGetTemplatePath(GreetingLanguage $language, string $expectedTemplatePath): void
    {
        $this->assertSame($expectedTemplatePath, $language->getTemplatePath());
    }

    /**
     * Poskytuje testovací data pro metodu testGetTemplatePath().
     * Obsahuje páry jazyk-cesta k šabloně pro všechny podporované jazyky.
     *
     * @return array<array<GreetingLanguage|string>>
     */
    public static function provideLanguageTemplatePaths(): array
    {
        return [
            [GreetingLanguage::Czech, 'emails/greeting/cs.html.twig'],
            [GreetingLanguage::English, 'emails/greeting/en.html.twig'],
            [GreetingLanguage::Russian, 'emails/greeting/ru.html.twig'],
        ];
    }

    /**
     * Testuje, zda všechny případy enumu mohou být vytvořeny.
     * Zjišťuje, zda enum obsahuje všechny 3 případy.
     */
    public function testAllCasesCanBeInstantiated(): void
    {
        $cases = GreetingLanguage::cases();
        $this->assertCount(3, $cases); // Zajistí, že jsou přítomny všechny 3 případy
    }

    /**
     * Testuje, zda všechny hodnoty enumu jsou jedinečné.
     * Zjišťuje, zda žádné dvě hodnoty nejsou stejné.
     */
    public function testUniqueValues(): void
    {
        $values = array_map(static fn ($case) => $case->value, GreetingLanguage::cases());
        $this->assertCount(\count($values), array_unique($values));
    }

    /**
     * Testuje, zda metody vrací správné typy návratových hodnot.
     * Zjišťuje, zda getSubject() a getTemplatePath() vrací řetězce.
     */
    public function testReturnTypes(): void
    {
        foreach (GreetingLanguage::cases() as $language) {
            /* @phpstan-ignore-next-line */
            $this->assertIsString($language->getSubject());
            /* @phpstan-ignore-next-line */
            $this->assertIsString($language->getTemplatePath());
        }
    }

    /**
     * Testuje formát cesty k šabloně.
     * Zjišťuje, zda cesta končí '.html.twig' a obsahuje kód jazyka.
     */
    public function testTemplatePathFormat(): void
    {
        foreach (GreetingLanguage::cases() as $language) {
            $this->assertStringEndsWith('.html.twig', $language->getTemplatePath());
            $this->assertStringContainsString($language->value, $language->getTemplatePath());
        }
    }

    /**
     * Testuje, zda metody vrací neprázdné řetězce.
     * Zjišťuje, zda getSubject() a getTemplatePath() vrací neprázdné hodnoty.
     */
    public function testNonEmptyStrings(): void
    {
        foreach (GreetingLanguage::cases() as $language) {
            $this->assertNotEmpty($language->getSubject());
            $this->assertNotEmpty($language->getTemplatePath());
        }
    }

    /**
     * Testuje, zda metoda from() vyhazuje výjimku při neplatné hodnotě.
     * Zjišťuje, zda je vyhozena výjimka ValueError při pokusu vytvořit enum z neplatného kódu jazyka.
     */
    public function testFromInvalidValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        GreetingLanguage::from('invalid');
    }

    /**
     * Testuje metodu tryFrom() s platnou a neplatnou hodnotou.
     * Zjišťuje, zda metoda správně vrací enum pro platný kód jazyka a null pro neplatný kód.
     */
    public function testTryFromValidAndInvalid(): void
    {
        $this->assertSame(GreetingLanguage::Czech, GreetingLanguage::tryFrom('cs'));
        /* @phpstan-ignore-next-line */
        $this->assertNull(GreetingLanguage::tryFrom('invalid'));
    }

    /**
     * Testuje rovnost enumů.
     * Zjišťuje, zda enumy jsou správně porovnávány a zda se liší různé případy.
     */
    public function testEquality(): void
    {
        $this->assertSame(GreetingLanguage::Czech, GreetingLanguage::from('cs'));
        $this->assertNotSame(GreetingLanguage::Czech, GreetingLanguage::English);
    }

    /**
     * Testuje serializaci enumu do JSON.
     * Zjišťuje, zda enum je správně serializován do JSON formátu.
     *
     * @throws \JsonException
     */
    public function testJsonSerialization(): void
    {
        $this->assertSame('"cs"', json_encode(GreetingLanguage::Czech, \JSON_THROW_ON_ERROR));
    }

    /**
     * Testuje jména a hodnoty všech případů enumu.
     * Zjišťuje, zda enum obsahuje správné jména a hodnoty pro všechny případy.
     */
    public function testCasesValuesAndNames(): void
    {
        $expected = [
            ['name' => 'Czech', 'value' => 'cs'],
            ['name' => 'English', 'value' => 'en'],
            ['name' => 'Russian', 'value' => 'ru'],
        ];
        $actual = array_map(static fn ($case) => [
            'name' => $case->name,
            'value' => $case->value,
        ], GreetingLanguage::cases());
        $this->assertSame($expected, $actual);
    }
}
