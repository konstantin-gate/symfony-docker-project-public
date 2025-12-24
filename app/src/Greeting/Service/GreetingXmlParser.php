<?php

declare(strict_types=1);

namespace App\Greeting\Service;

/**
 * Parser pro efektivní čtení XML souborů s e-maily (streamování).
 */
class GreetingXmlParser
{
    /**
     * Postupně načítá e-maily z XML souboru.
     *
     * @return \Generator<string>
     */
    public function parse(string $filePath): \Generator
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: $filePath");
        }

        // LIBXML_NONET zakazuje síťový přístup z bezpečnostních důvodů (prevence XXE)
        // Signatura PHP XMLReader::open: open(string $uri, ?string $encoding = null, int $options = 0)
        $reader = \XMLReader::open($filePath, null, \LIBXML_NONET);

        if (!$reader) {
            throw new \RuntimeException("Could not open XML file: $filePath");
        }

        $internalErrors = libxml_use_internal_errors(true);

        try {
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && strtolower($reader->localName) === 'email') {
                    $email = $reader->readString();

                    $validEmail = $this->processEmail($email);

                    if ($validEmail !== null) {
                        yield $validEmail;
                    }
                }
            }

            if (libxml_get_errors()) {
                $error = libxml_get_last_error();
                throw new \RuntimeException('Invalid XML: ' . ($error->message ?? 'unknown error'));
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Error parsing XML file: ' . $e->getMessage(), 0, $e);
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }
    }

    /**
     * Validuje a normalizuje e-mail (podpora IDN domén).
     */
    private function processEmail(string $email): ?string
    {
        $originalEmail = trim($email);

        if ($originalEmail === '') {
            return null;
        }

        $normalizedEmail = mb_strtolower($originalEmail);

        if (str_contains($normalizedEmail, '@')) {
            [$local, $domain] = explode('@', $normalizedEmail, 2);
            $asciiDomain = idn_to_ascii($domain, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46);

            if (false !== $asciiDomain) {
                $normalizedEmail = $local . '@' . $asciiDomain;
            }
        }

        if (filter_var($normalizedEmail, \FILTER_VALIDATE_EMAIL, \FILTER_FLAG_EMAIL_UNICODE)) {
            return $originalEmail;
        }

        return null;
    }
}
