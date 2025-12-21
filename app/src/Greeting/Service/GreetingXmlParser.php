<?php

declare(strict_types=1);

namespace App\Greeting\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class GreetingXmlParser
{
    /**
     * @return string[]
     */
    public function parse(UploadedFile $file): array
    {
        $emails = [];
        $content = file_get_contents($file->getPathname());

        if (!$content) {
            return [];
        }

        // Security: Explicitly disable external entity loading for libxml
        $backupEntityLoader = libxml_use_internal_errors(true);
        libxml_set_external_entity_loader(static fn () => null);

        try {
            $xml = simplexml_load_string($content);

            if (false === $xml) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $lastError = reset($errors);
                throw new \RuntimeException('Invalid XML: ' . ($lastError->message ?? 'unknown error'));
            }

            // Use XPath to consistently find all <email> tags
            $emailElements = $xml->xpath('//email');

            if (\is_array($emailElements)) {
                foreach ($emailElements as $element) {
                    $email = trim((string) $element);

                    if ($email !== '' && filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $email;
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Error parsing XML file: ' . $e->getMessage());
        } finally {
            // Restore libxml state
            libxml_use_internal_errors($backupEntityLoader);
            libxml_set_external_entity_loader(null);
        }

        return $emails;
    }
}
