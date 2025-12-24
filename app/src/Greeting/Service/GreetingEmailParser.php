<?php

declare(strict_types=1);

namespace App\Greeting\Service;

/**
 * Služba pro parsování a extrakci e-mailů z textového řetězce.
 */
class GreetingEmailParser
{
    /**
     * Převede řetězec s e-maily na pole unikátních, platných e-mailových adres.
     * Podporované oddělovače: čárka, mezera, nový řádek, středník.
     *
     * @return array<string>
     */
    public function parse(string $rawEmails): array
    {
        // Oddělovače: čárka, mezera, nový řádek, středník
        $emails = (array) preg_split('/[\s,;]+/', $rawEmails, -1, \PREG_SPLIT_NO_EMPTY);
        $uniqueEmails = array_unique($emails);

        /** @var array<string> $filteredEmails */
        $filteredEmails = array_filter(
            $uniqueEmails,
            static fn (mixed $email): bool => \is_string($email) && filter_var($email, \FILTER_VALIDATE_EMAIL) !== false
        );

        return array_values($filteredEmails);
    }
}
