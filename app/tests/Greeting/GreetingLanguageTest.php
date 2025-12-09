<?php

declare(strict_types=1);

namespace App\Tests\Greeting;

use App\Greeting\Enum\GreetingLanguage;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class GreetingLanguageTest extends TestCase
{
    /**
     * @param GreetingLanguage $language
     * @param string $expectedSubject
     */
    #[DataProvider('provideLanguageSubjects')]
    public function testGetSubject(GreetingLanguage $language, string $expectedSubject): void
    {
        $this->assertSame($expectedSubject, $language->getSubject());
    }

    public static function provideLanguageSubjects(): array
    {
        return [
            [GreetingLanguage::Czech, 'Veselé Vánoce a šťastný nový rok!'],
            [GreetingLanguage::English, 'Merry Christmas and Happy New Year!'],
            [GreetingLanguage::Russian, 'С Рождеством и Новым годом!'],
        ];
    }

    /**
     * @param GreetingLanguage $language
     * @param string $expectedTemplatePath
     */
    #[DataProvider('provideLanguageTemplatePaths')]
    public function testGetTemplatePath(GreetingLanguage $language, string $expectedTemplatePath): void
    {
        $this->assertSame($expectedTemplatePath, $language->getTemplatePath());
    }

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
        $this->assertContainsOnlyInstancesOf(GreetingLanguage::class, $cases);
        $this->assertCount(3, $cases); // Ensure all 3 cases are present
    }
}
