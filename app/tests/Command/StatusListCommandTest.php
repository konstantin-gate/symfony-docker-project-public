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
     * Základní smoke-test: příkaz jednoduše funguje a vrací 0.
     */
    public function testExecute(): void
    {
        $command = $this->application->find('app:status:list');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        // Základní kontrola hlavičky
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Status Reference Guide (Enum Status)', $output);
    }

    /**
     * Detailní test: ověřujeme, že každý řádek tabulky obsahuje
     * správná data z Enumu (barva, překlady, příznaky).
     */
    public function testExecuteShowsCompleteTable(): void
    {
        $command = $this->application->find('app:status:list');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        // Získáme celý výstup příkazu
        $output = $commandTester->getDisplay();

        // Rozdělíme výstup na řádky, abychom mohli kontrolovat data v kontextu jednoho řádku.
        $lines = explode("\n", $output);

        foreach (Status::cases() as $status) {
            // 1. Hledáme řádek v tabulce, který se týká aktuálního stavu.
            // Řádek by měl obsahovat název (Active) a hodnotu (active).
            $statusLine = null;

            foreach ($lines as $line) {
                if (str_contains($line, $status->name) && str_contains($line, $status->value)) {
                    $statusLine = $line;
                    break;
                }
            }

            $this->assertNotNull(
                $statusLine,
                \sprintf('Řádek pro stav "%s" nebyl nalezen v výstupu příkazu.', $status->name)
            );

            // 2. Ověřujeme klíč pro překlad
            $this->assertStringContainsString(
                $status->getTranslationKey(),
                $statusLine,
                \sprintf('V řádku stavu "%s" není klíč pro překlad.', $status->name)
            );

            // 3. Ověřujeme barvu (požadavek kolegy)
            $this->assertStringContainsString(
                $status->getColor(),
                $statusLine,
                \sprintf('V řádku stavu "%s" je nesprávná barva (očekával se %s).', $status->name, $status->getColor())
            );

            // 4. Ověřujeme viditelnost (požadavek kolegy)
            $expectedVisible = $status->isVisible() ? '+' : '-';
            $this->assertStringContainsString(
                $expectedVisible,
                $statusLine,
                \sprintf('Nesprávný příznak Visible pro stav "%s".', $status->name)
            );

            // 5. Ověřujeme upravitelnost (požadavek kolegy)
            $expectedEditable = $status->isEditable() ? '+' : '-';
            $this->assertStringContainsString(
                $expectedEditable,
                $statusLine,
                \sprintf('Nesprávný příznak Editable pro stav "%s".', $status->name)
            );
        }
    }

    /**
     * Kontrola pořadí a úplnosti dat v tabulce.
     * Ověřujeme, že řádky jdou přesně ve stejném pořadí jako v Enumu,
     * a jejich počet se shoduje.
     */
    public function testTableOrderAndCompleteness(): void
    {
        $command = $this->application->find('app:status:list');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);
        $output = $commandTester->getDisplay();

        // 1. Získáme referenční seznam názvů z Enumu v správném pořadí
        // Například: ['Concept', 'Active', 'Inactive', 'Archived', 'Deleted']
        $expectedOrder = array_map(static fn ($s) => $s->name, Status::cases());

        // 2. Parsovéme výstup příkazu, abychom našli, které stavy byly skutečně vygenerovány
        $foundOrder = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Procházíme očekávané stavy a sledujeme, zda aktuální řádek začíná názvem stavu.
            // Přidáváme mezeru k názvu, abychom předešli částečným shodám
            // (například aby ‚Active‘ nebyl shodován s ‚ActiveDeleted‘, pokud existuje).
            foreach ($expectedOrder as $name) {
                if (str_starts_with($trimmedLine, $name . ' ')) {
                    $foundOrder[] = $name;
                    break;
                }
            }
        }

        // 3. Ověřujeme počet (Úplnost)
        // Pokud v Enumu je 5 stavů, v tabulce by mělo být nalezeno přesně 5 řádků.
        $this->assertCount(
            \count($expectedOrder),
            $foundOrder,
            \sprintf(
                'Počet nalezených řádků (%d) neodpovídá počtu stavů v Enumu (%d).',
                \count($foundOrder),
                \count($expectedOrder)
            )
        );

        // 4. Ověřujeme pořadí
        // Pole by měla být identická: stejné hodnoty na stejných indexech.
        $this->assertSame(
            $expectedOrder,
            $foundOrder,
            'Pořadí zobrazení stavů v tabulce se liší od pořadí v Enum Status.'
        );
    }

    /**
     * Test pro prázdný Enum - příkaz by měl zobrazit pouze hlavičku tabulky, bez řádků a bez chyby.
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
     * Test pro Enum, který vyhazuje výjimku v metodě getColor()
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
     * Test pro validní Enum s vlastní třídou
     * (alternativa k původnímu testu, ale s explicitním předáním Enumu).
     */
    public function testExecuteWithCustomValidEnum(): void
    {
        // 1. Vytvoříme jednoduchý testovací Enum přímo v testu
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

        // 2. Vytvoříme příkaz s tímto Enumem
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
