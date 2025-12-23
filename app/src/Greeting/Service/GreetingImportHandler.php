<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\Greeting\Enum\GreetingLanguage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

readonly class GreetingImportHandler
{
    public function __construct(
        private GreetingXmlParser $greetingXmlParser,
        private GreetingEmailParser $greetingEmailParser,
        private GreetingContactService $greetingContactService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int Number of newly imported contacts
     *
     * @throws \Exception
     */
    public function handleImport(
        ?string $xmlFilePath,
        ?string $textContent,
        GreetingLanguage $language = GreetingLanguage::Russian,
    ): int {
        $count = 0;

        // 1. Parsing text field
        if ($textContent !== null && trim($textContent) !== '') {
            try {
                $textEmails = $this->greetingEmailParser->parse($textContent);
                $textEmails = array_values(array_unique($textEmails));

                if (!empty($textEmails)) {
                    $count += $this->greetingContactService->saveContacts($textEmails, $language);
                }
            } catch (\Exception $e) {
                $this->logger->error('Text parsing failed during import', [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        // 2. XML Parsing (Streaming)
        if ($xmlFilePath !== null) {
            try {
                $batch = [];
                $batchSize = 500;

                foreach ($this->greetingXmlParser->parse($xmlFilePath) as $email) {
                    $batch[] = $email;

                    if (\count($batch) >= $batchSize) {
                        $count += $this->greetingContactService->saveContacts($batch, $language);
                        $batch = [];

                        // Clear identity map to free memory during large imports
                        $this->entityManager->clear();
                    }
                }

                if (!empty($batch)) {
                    $count += $this->greetingContactService->saveContacts($batch, $language);
                    $this->entityManager->clear();
                }
            } catch (\Exception $e) {
                $this->logger->error('XML parsing/saving failed during import', [
                    'error' => $e->getMessage(),
                    'file' => $xmlFilePath,
                ]);
                throw $e;
            }
        }

        return $count;
    }
}
