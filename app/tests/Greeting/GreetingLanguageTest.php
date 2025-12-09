<?php

declare(strict_types=1);

namespace App\Tests\Greeting;

use App\Greeting\Enum\GreetingLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GreetingLanguageTest extends TestCase
{
    #[DataProvider('provideLanguageSubjects')]
    public function testGetSubject(GreetingLanguage $language, string $expectedSubject): void
    {
        $this->assertSame($expectedSubject, $language->getSubject());
    }

    /**
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

    #[DataProvider('provideLanguageTemplatePaths')]
    public function testGetTemplatePath(GreetingLanguage $language, string $expectedTemplatePath): void
    {
        $this->assertSame($expectedTemplatePath, $language->getTemplatePath());
    }

    /**
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

    public function testAllCasesCanBeInstantiated(): void
    {
        $cases = GreetingLanguage::cases();
        $this->assertCount(3, $cases); // Ensure all 3 cases are present
    }

    public function testUniqueValues(): void
    {
        $values = array_map(static fn ($case) => $case->value, GreetingLanguage::cases());
        $this->assertCount(\count($values), array_unique($values));
    }

    public function testReturnTypes(): void
    {
        foreach (GreetingLanguage::cases() as $language) {
            /* @phpstan-ignore-next-line */
            $this->assertIsString($language->getSubject());
            /* @phpstan-ignore-next-line */
            $this->assertIsString($language->getTemplatePath());
        }
    }

    public function testTemplatePathFormat(): void
    {
        foreach (GreetingLanguage::cases() as $language) {
            $this->assertStringEndsWith('.html.twig', $language->getTemplatePath());
            $this->assertStringContainsString($language->value, $language->getTemplatePath());
        }
    }

    public function testNonEmptyStrings(): void
    {
        foreach (GreetingLanguage::cases() as $language) {
            $this->assertNotEmpty($language->getSubject());
            $this->assertNotEmpty($language->getTemplatePath());
        }
    }

    public function testFromInvalidValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        GreetingLanguage::from('invalid');
    }

    public function testTryFromValidAndInvalid(): void
    {
        $this->assertSame(GreetingLanguage::Czech, GreetingLanguage::tryFrom('cs'));
        /* @phpstan-ignore-next-line */
        $this->assertNull(GreetingLanguage::tryFrom('invalid'));
    }

    public function testEquality(): void
    {
        $this->assertSame(GreetingLanguage::Czech, GreetingLanguage::from('cs'));
        $this->assertNotSame(GreetingLanguage::Czech, GreetingLanguage::English);
    }

    /**
     * @throws \JsonException
     */
    public function testJsonSerialization(): void
    {
        $this->assertSame('"cs"', json_encode(GreetingLanguage::Czech, \JSON_THROW_ON_ERROR));
    }

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
