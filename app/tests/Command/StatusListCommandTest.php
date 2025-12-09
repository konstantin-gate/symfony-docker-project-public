<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\StatusListCommand;
use App\Enum\Status;
use App\Tests\TestFixtures\EmptyStatusEnum;
use App\Tests\TestFixtures\FailingStatusEnum;
use App\Tests\TestFixtures\StatusInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class StatusListCommandTest extends KernelTestCase
{
    protected Application $application;
    protected ?CommandTester $commandTester = null;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();
        $this->application = new Application($kernel);
    }

    /**
     * Базовый smoke-тест: команда просто работает и возвращает 0.
     */
    public function testExecute(): void
    {
        $command = $this->application->find('app:status:list');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        // Базовая проверка заголовка
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Status Reference Guide (Enum Status)', $output);
    }

    /**
     * Детальный тест: проверяем, что каждая строка таблицы содержит
     * верные данные из Enum (цвет, переводы, флаги).
     */
    public function testExecuteShowsCompleteTable(): void
    {
        $command = $this->application->find('app:status:list');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        // Получаем весь вывод команды
        $output = $commandTester->getDisplay();

        // Разбиваем вывод на строки, чтобы проверять данные в контексте одной строки
        $lines = explode("\n", $output);

        foreach (Status::cases() as $status) {
            // 1. Ищем строку в таблице, которая относится к текущему статусу.
            // Строка должна содержать имя (Active) и значение (active).
            $statusLine = null;

            foreach ($lines as $line) {
                if (str_contains($line, $status->name) && str_contains($line, $status->value)) {
                    $statusLine = $line;
                    break;
                }
            }

            $this->assertNotNull(
                $statusLine,
                \sprintf('Строка для статуса "%s" не найдена в выводе команды.', $status->name)
            );

            // 2. Проверяем Translation Key
            $this->assertStringContainsString(
                $status->getTranslationKey(),
                $statusLine,
                \sprintf('В строке статуса "%s" нет ключа перевода.', $status->name)
            );

            // 3. Проверяем Color (Colleague's request)
            $this->assertStringContainsString(
                $status->getColor(),
                $statusLine,
                \sprintf('В строке статуса "%s" неверный цвет (ожидался %s).', $status->name, $status->getColor())
            );

            // 4. Проверяем Visible (Colleague's request)
            $expectedVisible = $status->isVisible() ? '+' : '-';
            $this->assertStringContainsString(
                $expectedVisible,
                $statusLine,
                \sprintf('Неверный флаг Visible для статуса "%s".', $status->name)
            );

            // 5. Проверяем Editable (Colleague's request)
            $expectedEditable = $status->isEditable() ? '+' : '-';
            $this->assertStringContainsString(
                $expectedEditable,
                $statusLine,
                \sprintf('Неверный флаг Editable для статуса "%s".', $status->name)
            );
        }
    }

    /**
     * Проверка порядка (Pořadí) и полноты (Úplnost) данных в таблице.
     * Мы убеждаемся, что строки идут ровно в том порядке, как в Enum,
     * и их количество совпадает.
     */
    public function testTableOrderAndCompleteness(): void
    {
        $command = $this->application->find('app:status:list');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);
        $output = $commandTester->getDisplay();

        // 1. Получаем эталонный список имен из Enum в правильном порядке
        // Например: ['Concept', 'Active', 'Inactive', 'Archived', 'Deleted']
        $expectedOrder = array_map(static fn ($s) => $s->name, Status::cases());

        // 2. Парсим вывод команды, чтобы найти, какие статусы реально были выведены
        $foundOrder = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Пробегаемся по ожидаемым статусам и смотрим, начинается ли текущая строка с имени статуса.
            // Добавляем пробел к имени, чтобы избежать частичных совпадений
            // (например, чтобы 'Active' не совпал с 'ActiveDeleted', если такой будет).
            foreach ($expectedOrder as $name) {
                if (str_starts_with($trimmedLine, $name . ' ')) {
                    $foundOrder[] = $name;
                    break; // Переходим к следующей строке вывода
                }
            }
        }

        // 3. Проверяем количество (Úplnost)
        // Если в Enum 5 статусов, в таблице должно быть найдено ровно 5 строк.
        $this->assertCount(
            \count($expectedOrder),
            $foundOrder,
            \sprintf(
                'Количество найденных строк (%d) не совпадает с количеством статусов в Enum (%d).',
                \count($foundOrder),
                \count($expectedOrder)
            )
        );

        // 4. Проверяем порядок (Pořadí)
        // Массивы должны быть идентичны: одинаковые значения на одинаковых индексах.
        $this->assertSame(
            $expectedOrder,
            $foundOrder,
            'Порядок вывода статусов в таблице отличается от порядка в Enum Status.'
        );
    }

    /**
     * Test pro prázdný enum - příkaz by měl zobrazit pouze hlavičku tabulky, bez řádků a bez chyby.
     */
    public function testExecuteWithEmptyEnum(): void
    {
        // Vytvoříme instanci příkazu s testovacím enumem (obcházíme DI kontejner)
        $command = new StatusListCommand(EmptyStatusEnum::class);

        // Vytvoříme CommandTester
        $commandTester = new CommandTester($command);

        // Spustíme příkaz
        $commandTester->execute([]);

        // Ověříme, že příkaz skončil úspěšně
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Získáme výstup
        $output = $commandTester->getDisplay();

        // Assert: Příkaz zobrazil hlavičku
        $this->assertStringContainsString('Status Reference Guide (Enum Status)', $output);
        $this->assertStringContainsString('Case Name', $output);
        $this->assertStringContainsString('DB Value', $output);

        // Assert: V tabulce nejsou žádná data (jen hlavička)
        // Můžeme ověřit, že se nezobrazují žádná data z reálného enumu
        $this->assertStringNotContainsString('Active', $output);
        $this->assertStringNotContainsString('active', $output);
    }

    /**
     * Test pro enum, který vyhazuje výjimku v metodě getColor()
     * Očekáváme, že příkaz selže s výjimkou.
     */
    public function testExecuteWithFailingEnum(): void
    {
        // Vytvoříme instanci příkazu s enumem, který selže
        $command = new StatusListCommand(FailingStatusEnum::class);

        // Vytvoříme CommandTester
        $commandTester = new CommandTester($command);

        // Očekáváme, že příkaz vyhodí RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed while fetching color');

        // Spustíme příkaz (vyhodí výjimku)
        $commandTester->execute([]);
    }

    /**
     * Test pro validní enum s vlastní třídou
     * (alternativa k původnímu testu, ale s explicitním předáním enumu).
     */
    public function testExecuteWithCustomValidEnum(): void
    {
        // 1. Vytvoříme jednoduchý testovací enum přímo v testu
        $testEnumClass = new class {
            public const string TEST = 'TEST';

            /**
             * @return array<StatusInterface>
             */
            public static function cases(): array
            {
                return [
                    new class implements StatusInterface {
                        public string $name = 'ACTIVE';
                        public string $value = 'active';

                        public function getTranslationKey(): string
                        {
                            return 'status.active';
                        }

                        public function getColor(): string
                        {
                            return 'green';
                        }

                        public function isVisible(): bool
                        {
                            return true;
                        }

                        public function isEditable(): bool
                        {
                            return true;
                        }
                    },
                ];
            }
        };

        // 2. Vytvoříme příkaz s tímto enumem
        $command = new StatusListCommand($testEnumClass::class);
        $commandTester = new CommandTester($command);

        // 3. Spustíme příkaz
        $commandTester->execute([]);

        // 4. Ověříme výstup
        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        // 5. Assert: Data z testovacího enumu jsou zobrazena
        $this->assertStringContainsString('ACTIVE', $output);
        $this->assertStringContainsString('active', $output);
        $this->assertStringContainsString('status.active', $output);
        $this->assertStringContainsString('green', $output);
        $this->assertStringContainsString('+', $output); // Pro visible a editable
    }
}
