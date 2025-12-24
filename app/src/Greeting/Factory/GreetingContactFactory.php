<?php

declare(strict_types=1);

namespace App\Greeting\Factory;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;

/**
 * Továrna pro vytváření instancí GreetingContact.
 */
class GreetingContactFactory
{
    /**
     * Vytvoří nový kontakt s danými parametry a výchozím stavem Active.
     */
    public function create(
        string $email,
        GreetingLanguage $language,
        \DateTimeImmutable $registrationDate,
    ): GreetingContact {
        $contact = new GreetingContact();
        $contact->setEmail($email);
        $contact->setLanguage($language);
        $contact->setCreatedAt($registrationDate);
        $contact->setStatus(Status::Active);

        return $contact;
    }
}
