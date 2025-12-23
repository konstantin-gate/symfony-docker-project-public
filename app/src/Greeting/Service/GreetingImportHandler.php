<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use App\Greeting\DTO\GreetingImportResult;
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

    public function handleImport(
        ?string $xmlFilePath,
        ?string $textContent,
        GreetingLanguage $language = GreetingLanguage::Russian,
    ): GreetingImportResult {
        $count = 0;
        $hasData = false;

        // 1. Parsing text field
        if ($textContent !== null && trim($textContent) !== '') {
            $hasData = true;
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

                return GreetingImportResult::failure('import.error_validation');
            }
        }

        // 2. XML Parsing (Streaming)
        if ($xmlFilePath !== null) {
            $hasData = true;
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

                return GreetingImportResult::failure('import.error_xml_parsing');
            }
        }

        if (!$hasData) {
            return GreetingImportResult::failure('import.error_no_data');
        }

        return GreetingImportResult::success($count);
    }
}
