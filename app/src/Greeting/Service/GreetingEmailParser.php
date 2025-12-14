<?php

declare(strict_types=1);

namespace App\Greeting\Service;

class GreetingEmailParser
{
    /**
     * Parse a string of emails into an array of unique, valid email addresses.
     * Supported separators: comma, space, newline, semicolon.
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
