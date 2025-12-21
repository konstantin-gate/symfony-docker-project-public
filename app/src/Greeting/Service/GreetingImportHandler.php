<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\Greeting\Enum\GreetingLanguage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class GreetingImportHandler
{
    public function __construct(
        private GreetingEmailParser $greetingEmailParser,
        private GreetingXmlParser $greetingXmlParser,
        private GreetingContactService $greetingContactService,
    ) {
    }

    /**
     * @param array{emails: ?string, language: GreetingLanguage, registrationDate: \DateTime} $data
     *
     * @return int Number of newly imported emails
     */
    public function handleImport(array $data, ?UploadedFile $xmlFile): int
    {
        $emails = [];

        // 1. Process Text Input
        if (!empty($data['emails'])) {
            $emails = $this->greetingEmailParser->parse($data['emails']);
        }

        // 2. Process XML File
        if ($xmlFile) {
            $xmlEmails = $this->greetingXmlParser->parse($xmlFile);
            $emails = array_merge($emails, $xmlEmails);
        }

        $emails = array_unique($emails);

        if (empty($emails)) {
            return -1; // Special value indicating no data was provided
        }

        return $this->greetingContactService->importContacts(
            $emails,
            $data['language'],
            \DateTimeImmutable::createFromMutable($data['registrationDate'])
        );
    }
}
