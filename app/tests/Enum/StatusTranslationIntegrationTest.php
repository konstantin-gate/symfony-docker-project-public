<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\Status;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

class StatusTranslationIntegrationTest extends TestCase
{
    private ?Translator $translator = null;
    private Filesystem $filesystem;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        // Vytvoříme dočasný adresář pro překladové soubory
        $this->tempDir = sys_get_temp_dir() . '/status_translation_test_' . uniqid('', true);
        $this->filesystem->mkdir($this->tempDir);

        // Cesta k dočasnému překladovému souboru
        $translationFile = $this->tempDir . '/statuses.ru.yaml';

        // Obsah překladového souboru
        $yamlContent = <<<'YAML'
status:
    concept: 'Концепт'
    active: 'Активный'
    inactive: 'Неактивный'
    archived: 'Архивный'
    deleted: 'Удален'
YAML;

        // Zapisujeme soubor
        $this->filesystem->dumpFile($translationFile, $yamlContent);

        // Inicializujeme skutečný Translator
        $this->translator = new Translator(
            'ru' // Výchozí lokalita pro testy
        );
        $this->translator->addLoader('yaml', new YamlFileLoader()); // Přidáme zavaděč YAML

        // Přidáme zdroj překladu
        $this->translator->addResource(
            'yaml',
            $translationFile,
            'ru',
            'statuses' // Doména musí odpovídat hodnotě v Enum\Status::trans().
        );
        $this->translator->setLocale('ru');
    }

    protected function tearDown(): void
    {
        // Vymažeme dočasný adresář a soubory
        if (isset($this->tempDir) && $this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
        $this->translator = null;
    }

    /**
     * Testuje metodu trans() s reálným překladačem a načtenými překlady YAML.
     */
    public function testTransWithRealTranslatorAndYamlTranslations(): void
    {
        $translator = $this->translator;
        $this->assertNotNull($translator, 'Translator should be initialized.');

        // Kontrolujeme překlad pro aktivní status
        $translated = Status::Active->trans($translator, 'ru');
        $this->assertSame('Активный', $translated);

        // Kontrolujeme překlad pro koncept
        $translated = Status::Concept->trans($translator, 'ru');
        $this->assertSame('Концепт', $translated);

        // Ověřujeme, že pokud překlad není nalezen, vrátí se klíč (standardní chování Translatoru).
        // K tomu je třeba buď nepřidávat žádný klíč do YAML, nebo předat neexistující klíč.
        // Nyní jsou všechny klíče k dispozici, takže tento případ nebude fungovat.
        // Pokud by klíč ‚status.nonexistent‘ chyběl, vrátil by se ‚status.nonexistent‘.

        // Zkontrolujeme, že pokud předáme jinou lokalitu, vrátí se původní klíč (protože máme pouze ‚ru‘).
        $translatedFallback = Status::Active->trans($translator, 'en');
        $this->assertSame('status.active', $translatedFallback);
    }
}
