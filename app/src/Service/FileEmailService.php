<?php

declare(strict_types=1);

namespace App\Service;

use Random\RandomException;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Služba pro ukládání e-mailů do souborů namísto jejich odesílání přes SMTP.
 */
class FileEmailService extends AbstractEmailService
{
    private string $mailsDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        private readonly Environment $twig,
        string $senderEmail,
        string $senderName,
    ) {
        parent::__construct($senderEmail, $senderName);
        $this->mailsDir = rtrim($projectDir, '/') . '/var/mails';

        if (!file_exists($this->mailsDir)
            && !mkdir($concurrentDirectory = $this->mailsDir, 0777, true)
            && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(\sprintf('Adresář "%s" nebyl vytvořen', $concurrentDirectory));
        }
    }

    /**
     * Uloží e-mailovou zprávu do souboru.
     *
     * @throws RandomException
     */
    protected function sendEmail(TemplatedEmail $email): void
    {
        // Vykreslení těla e-mailu (Twig -> HTML/Text)
        $renderer = new BodyRenderer($this->twig);
        $renderer->render($email);

        $filename = $this->generateFilename();
        $filepath = $this->mailsDir . '/' . $filename;

        // Uložení kompletního EML zdroje
        file_put_contents($filepath, $email->toString());
    }

    /**
     * Generuje unikátní název souboru pro e-mail.
     *
     * @throws RandomException
     */
    private function generateFilename(): string
    {
        return \sprintf(
            'email_%s_%s.eml',
            date('Y-m-d_H-i-s'),
            substr(md5(uniqid((string) random_int(0, mt_getrandmax()), true)), 0, 8)
        );
    }
}
