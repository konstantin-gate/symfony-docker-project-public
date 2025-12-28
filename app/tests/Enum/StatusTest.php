<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Testovací třída pro enum Status.
 * Ověřuje atributy enumu, jako jsou klíče pro překlad, barvy, viditelnost a další vlastnosti, včetně integrace s překladatelem.
 */
class StatusTest extends TestCase
{
    /**
     * Testuje getTranslationKey, getColor, isVisible, isEditable a isRecoverable
     * pro každou variantu Enum.
     */
    #[DataProvider('provideEnumCases')]
    public function testEnumAttributes(
        Status $status,
        string $expectedKey,
        string $expectedColor,
        bool $expectedVisible,
        bool $expectedEditable,
        bool $expectedRecoverable,
    ): void {
        $this->assertSame($expectedKey, $status->getTranslationKey());
        $this->assertSame($expectedColor, $status->getColor());
        $this->assertSame($expectedVisible, $status->isVisible());
        $this->assertSame($expectedEditable, $status->isEditable());
        $this->assertSame($expectedRecoverable, $status->isRecoverable());
    }

    public static function provideEnumCases(): \Generator
    {
        // Status | Key | Color | Visible | Editable | Recoverable
        yield 'Concept' => [
            Status::Concept,
            'status.concept',
            'warning',
            false, // isVisible
            true,  // isEditable
            false, // isRecoverable
        ];

        yield 'Active' => [
            Status::Active,
            'status.active',
            'success',
            true,  // isVisible
            true,  // isEditable
            false, // isRecoverable
        ];

        yield 'Inactive' => [
            Status::Inactive,
            'status.inactive',
            'secondary',
            false, // isVisible
            true,  // isEditable
            false, // isRecoverable
        ];

        yield 'Archived' => [
            Status::Archived,
            'status.archived',
            'info',
            false, // isVisible
            false, // isEditable
            true,  // isRecoverable
        ];

        yield 'Deleted' => [
            Status::Deleted,
            'status.deleted',
            'danger',
            false, // isVisible
            false, // isEditable
            true,  // isRecoverable
        ];
    }

    /**
     * Ověřuje, že Enum implementuje požadované rozhraní a má správné hodnoty (backing values).
     */
    public function testEnumBasics(): void
    {
        $this->assertInstanceOf(TranslatableInterface::class, Status::Active);

        $this->assertCount(5, Status::cases());
        $this->assertSame('concept', Status::Concept->value);
        $this->assertSame('active', Status::Active->value);
        $this->assertSame('inactive', Status::Inactive->value);
        $this->assertSame('archived', Status::Archived->value);
        $this->assertSame('deleted', Status::Deleted->value);

        // Проверка успешных преобразований
        $this->assertSame(Status::Active, Status::from('active'));
        $this->assertSame(Status::Concept, Status::tryFrom('concept'));

        // Проверка соответствия количества кейсов Enum и данных в DataProvider
        $this->assertCount(
            \count(Status::cases()),
            iterator_to_array(self::provideEnumCases()),
            'Mismatch between number of Enum cases and data provider entries. Did you update provideEnumCases()?'
        );
    }

    /**
     * Testuje, že metoda from() vyvolá výjimku ValueError pro nepřípustnou hodnotu.
     */
    public function testFromThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('"invalid" is not a valid backing value for enum App\\Enum\\Status');
        $unused = Status::from('invalid');
        $this->assertInstanceOf(Status::class, $unused);
    }

    /**
     * Testuje, zda metoda tryFrom() vrací hodnotu null pro nepřípustnou hodnotu.
     */
    public function testTryFromReturnsNullForInvalidValue(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertNull(Status::tryFrom('nonexistent'));
    }

    /**
     * Testuje integraci s TranslatableInterface (s explicitní lokalizací).
     */
    public function testTrans(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);

        $translator->expects($this->once())
            ->method('trans')
            ->with('status.active', [], 'statuses', 'en')
            ->willReturn('Active Status');

        $result = Status::Active->trans($translator, 'en');

        $this->assertSame('Active Status', $result);
    }

    /**
     * Testuje integraci s TranslatableInterface (bez zadání lokalizace – výchozí chování).
     */
    public function testTransWithDefaultLocale(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);

        // Ожидаем вызов с null в качестве локали
        $translator->expects($this->once())
            ->method('trans')
            ->with('status.active', [], 'statuses', null)
            ->willReturn('Active Status');

        $result = Status::Active->trans($translator);

        $this->assertSame('Active Status', $result);
    }

    /**
     * Testuje statickou metodu getChoices (úplná kontrola).
     */
    public function testGetChoices(): void
    {
        $choices = Status::getChoices();

        $this->assertCount(5, $choices);

        foreach (Status::cases() as $case) {
            $key = $case->getTranslationKey();

            // Ověřujeme, že klíč existuje
            $this->assertArrayHasKey($key, $choices, "Choice array missing key: $key");

            // Ověřujeme, zda hodnota odpovídá klíči
            $this->assertSame($case, $choices[$key], "Value mismatch for key: $key");
        }
    }

    /**
     * Testuje situaci, kdy překladatel vrátí prázdný řádek.
     */
    public function testTransReturnsEmptyString(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->willReturn('');

        $this->assertSame('', Status::Concept->trans($translator));
    }

    /**
     * Testuje, zda jsou výjimky vyhozené překladačem správně předávány.
     */
    public function testTransPropagatesException(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->willThrowException(new \RuntimeException('Translator error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Translator error');

        Status::Active->trans($translator);
    }
}
