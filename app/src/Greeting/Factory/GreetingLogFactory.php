<?php

declare(strict_types=1);

namespace App\Greeting\Factory;

use App\Greeting\Entity\GreetingContact;
use App\Greeting\Entity\GreetingLog;

/**
 * Továrna pro vytváření instancí GreetingLog.
 */
class GreetingLogFactory
{
    /**
     * Vytvoří nový log odeslání pro aktuální rok.
     */
    public function create(GreetingContact $contact): GreetingLog
    {
        return new GreetingLog($contact, (int) date('Y'));
    }
}
