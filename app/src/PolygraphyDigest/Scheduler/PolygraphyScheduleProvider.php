<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Scheduler;

use App\PolygraphyDigest\Message\TriggerSourceCheckMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Plánovač pro modul PolygraphyDigest.
 * Definuje pravidelné úlohy, které se mají spouštět.
 */
#[AsSchedule('polygraphy')]
final class PolygraphyScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->stateful($this->cache)
            ->add(
                // Spouštět kontrolu zdrojů každou minutu
                RecurringMessage::every('1 minute', new TriggerSourceCheckMessage())
            );
    }
}
