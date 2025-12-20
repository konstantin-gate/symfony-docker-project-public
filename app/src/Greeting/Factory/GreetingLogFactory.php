<?php

declare(strict_types=1);

namespace App\Greeting\Factory;

use App\Greeting\Entity\GreetingContact;
use App\Greeting\Entity\GreetingLog;

class GreetingLogFactory
{
    public function create(GreetingContact $contact): GreetingLog
    {
        return new GreetingLog($contact, (int) date('Y'));
    }
}
