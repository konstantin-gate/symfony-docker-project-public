<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Repository\GreetingContactRepository;

readonly class GreetingService
{
    public function __construct(
        private GreetingContactRepository $greetingContactRepository,
    ) {
    }

    /**
     * @return array<string, array<int, GreetingContact>>
     */
    public function getContactsGroupedByLanguage(): array
    {
        // Získáme všechny aktivní kontakty
        $contacts = $this->greetingContactRepository->findBy(['status' => Status::Active], ['email' => 'ASC']);

        // Seskupíme podle jazyka
        $groupedContacts = [];

        foreach (GreetingLanguage::cases() as $langEnum) {
            $groupedContacts[$langEnum->value] = [];
        }

        foreach ($contacts as $contact) {
            $lang = $contact->getLanguage()->value;
            $groupedContacts[$lang][] = $contact;
        }

        // Ensure internal sorting for each group to be robust against repository changes
        foreach ($groupedContacts as &$group) {
            usort($group, static fn (GreetingContact $a, GreetingContact $b) => $a->getEmail() <=> $b->getEmail());
        }

        return $groupedContacts;
    }
}
