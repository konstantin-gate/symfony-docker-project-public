<?php

namespace App\DataFixtures;

use App\PolygraphyDigest\Entity\Source;
use App\PolygraphyDigest\Enum\SourceTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture třída pro naplnění databáze výchozími zdroji pro modul PolygraphyDigest.
 * Slouží k vytvoření počátečních RSS kanálů (např. Grafika.cz, PrintProgress.cz),
 * které bude systém následně parsovat.
 */
class PolygraphySourceFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * Definuje skupiny, do kterých tato fixture patří.
     * Umožňuje spouštět pouze fixtures pro tento modul příkazem:
     * php bin/console doctrine:fixtures:load --group=polygraphy
     *
     * @return array<string> Seznam skupin
     */
    public static function getGroups(): array
    {
        return ['polygraphy'];
    }

    /**
     * Načte a uloží definované zdroje (Source) do databáze.
     * Prochází seznam předdefinovaných zdrojů, vytváří entity Source, nastavuje jejich vlastnosti
     * (název, URL, typ, plánovač) a ukládá je pomocí ObjectManageru.
     *
     * @param ObjectManager $manager Manažer entit pro zápis do databáze
     */
    public function load(ObjectManager $manager): void
    {
        $sources = [
            [
                'name' => 'Grafika.cz',
                'url' => 'https://www.grafika.cz/rubriky/tisk/rss/',
                'type' => SourceTypeEnum::RSS,
                'schedule' => '*/30 * * * *', // Каждые 30 минут
            ],
            [
                'name' => 'PrintProgress.cz',
                'url' => 'https://www.printprogress.cz/feed/',
                'type' => SourceTypeEnum::RSS,
                'schedule' => '0 * * * *', // Раз в час
            ],
            [
                'name' => 'DesignPortál',
                'url' => 'https://www.designportal.cz/feed/',
                'type' => SourceTypeEnum::RSS,
                'schedule' => '0 8,14,20 * * *', // Утром, днем и вечером
            ],
            [
                'name' => 'CzechDesign',
                'url' => 'https://www.czechdesign.cz/rss/articles',
                'type' => SourceTypeEnum::RSS,
                'schedule' => '0 9 * * *', // Раз в день в 9 утра
            ],
            [
                'name' => 'Font.cz',
                'url' => 'https://www.font.cz/rss.html',
                'type' => SourceTypeEnum::RSS,
                'schedule' => '0 10 * * *', // Раз в день в 10 утра
            ],
            // Запасной англоязычный источник для объема
            [
                'name' => 'PrintWeek (EN)',
                'url' => 'https://www.printweek.com/rss/news',
                'type' => SourceTypeEnum::RSS,
                'schedule' => '*/45 * * * *',
            ],
        ];

        foreach ($sources as $data) {
            $source = new Source();
            $source->setName($data['name']);
            $source->setUrl($data['url']);
            $source->setType($data['type']);
            $source->setActive(true);
            $source->setSchedule($data['schedule']);
            
            $manager->persist($source);
        }

        $manager->flush();
    }
}
