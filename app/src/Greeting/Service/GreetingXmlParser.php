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

            // Use XPath with local-name() to find <email> tags regardless of namespaces
            $emailElements = $xml->xpath('//*[local-name()="email"]');

            if (\is_array($emailElements)) {
                foreach ($emailElements as $element) {
                    $originalEmail = trim((string) $element);

                    if ($originalEmail === '') {
                        continue;
                    }

                    // Normalize for validation and duplicate check
                    $normalizedEmail = mb_strtolower($originalEmail);

                    if (str_contains($normalizedEmail, '@')) {
                        [$local, $domain] = explode('@', $normalizedEmail, 2);
                        $asciiDomain = idn_to_ascii($domain, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46);

                        if (false !== $asciiDomain) {
                            $normalizedEmail = $local . '@' . $asciiDomain;
                        }
                    }

                    // Use normalized email as key to prevent duplicates, but keep original email as value
                    if (!isset($emails[$normalizedEmail])
                        && filter_var($normalizedEmail, \FILTER_VALIDATE_EMAIL, \FILTER_FLAG_EMAIL_UNICODE)) {
                        $emails[$normalizedEmail] = $originalEmail;
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

        return array_values($emails);
    }
}
