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
        // Создаем временную директорию для файлов переводов
        $this->tempDir = sys_get_temp_dir() . '/status_translation_test_' . uniqid('', true);
        $this->filesystem->mkdir($this->tempDir);

        // Путь к временному файлу перевода
        $translationFile = $this->tempDir . '/statuses.ru.yaml';

        // Содержимое файла перевода
        $yamlContent = <<<'YAML'
status:
    concept: 'Концепт'
    active: 'Активный'
    inactive: 'Неактивный'
    archived: 'Архивный'
    deleted: 'Удален'
YAML;

        // Записываем файл
        $this->filesystem->dumpFile($translationFile, $yamlContent);

        // Инициализируем настоящий Translator
        $this->translator = new Translator(
            'ru' // Локаль по умолчанию для тестов
        );
        $this->translator->addLoader('yaml', new YamlFileLoader()); // Добавляем загрузчик YAML

        // Добавляем ресурс перевода
        $this->translator->addResource(
            'yaml',
            $translationFile,
            'ru',
            'statuses' // Домен должен совпадать с тем, что в Enum\Status::trans()
        );
        $this->translator->setLocale('ru');
    }

    protected function tearDown(): void
    {
        // Очищаем временную директорию и файлы
        if (isset($this->tempDir) && $this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
        $this->translator = null;
    }

    /**
     * Тестирует метод trans() с реальным Translator и загруженными YAML переводами.
     */
    public function testTransWithRealTranslatorAndYamlTranslations(): void
    {
        $translator = $this->translator;
        $this->assertNotNull($translator, 'Translator should be initialized.');

        // Проверяем перевод для активного статуса
        $translated = Status::Active->trans($translator, 'ru');
        $this->assertSame('Активный', $translated);

        // Проверяем перевод для концепта
        $translated = Status::Concept->trans($translator, 'ru');
        $this->assertSame('Концепт', $translated);

        // Проверяем, что если перевод не найден, возвращается ключ (стандартное поведение Translator)
        // Для этого нужно либо не добавлять какой-то ключ в YAML, либо передать несуществующий ключ.
        // Сейчас все ключи есть, так что этот кейс не сработает.
        // Если бы ключ 'status.nonexistent' отсутствовал, то вернулся бы 'status.nonexistent'.

        // Проверим, что если передать другую локаль, то вернется оригинальный ключ (так как у нас есть только 'ru')
        $translatedFallback = Status::Active->trans($translator, 'en');
        $this->assertSame('status.active', $translatedFallback);
    }
}
