<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\Greeting\Enum\GreetingLanguage;
use Psr\Log\LoggerInterface;

readonly class GreetingImportHandler
{
    public function __construct(
        private GreetingXmlParser $greetingXmlParser,
        private GreetingEmailParser $greetingEmailParser,
        private GreetingContactService $greetingContactService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int Number of newly imported contacts
     *
     * @throws \Exception
     */
    public function handleImport(?string $xmlContent, ?string $textContent, GreetingLanguage $language = GreetingLanguage::Russian): int
    {
        $emails = [];

        // 1. Парсинг XML
        if ($xmlContent !== null && trim($xmlContent) !== '') {
            try {
                $xmlEmails = $this->greetingXmlParser->parse($xmlContent);
                $emails = array_merge($emails, $xmlEmails);
            } catch (\Exception $e) {
                $this->logger->error('XML parsing failed during import', [
                    'error' => $e->getMessage(),
                    'content_snippet' => mb_substr($xmlContent, 0, 100),
                ]);
                throw $e;
            }
        }

        // 2. Парсинг текстового поля
        if ($textContent !== null && trim($textContent) !== '') {
            try {
                $textEmails = $this->greetingEmailParser->parse($textContent);
                $emails = array_merge($emails, $textEmails);
            } catch (\Exception $e) {
                $this->logger->error('Text parsing failed during import', [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $emails = array_values(array_unique($emails));

        if (empty($emails)) {
            return 0;
        }

        try {
            // 3. Сохранение
            return $this->greetingContactService->saveContacts($emails, $language);
        } catch (\Exception $e) {
            $this->logger->critical('Database error during contact import', [
                'error' => $e->getMessage(),
                'email_count' => \count($emails),
            ]);
            throw $e;
        }
    }
}
