<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Twig\Error\LoaderError;

class EmailServiceIntegrationTest extends KernelTestCase
{
    private EmailService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // Ensure service is public or accessible via aliases if needed,
        // but in tests we can usually access private services via test container.
        $this->service = $container->get(EmailService::class);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testSendsEmailWithRealTemplate(): void
    {
        // Use a real template existing in the project: templates/email/greeting.html.twig
        // as seen in the file structure context.
        $template = 'email/greeting.html.twig';
        $to = 'integration@example.com';
        $subject = 'Integration Test';

        // Context required by the template:
        // 'subject' is used in {% block title %}{{ subject }}{% endblock %}
        // 'body' is used in {{ body|raw }}
        $context = [
            'name' => 'Tester',
            'subject' => $subject,
            'body' => '<p>This is a test body.</p>',
        ];

        $this->service->send($to, $subject, $template, $context);

        // Assert email was sent (queued)
        // Note: KernelTestCase doesn't include MailerAssertionsTrait by default in older versions,
        // but often it's available if we use WebTestCase or include the trait manually.
        // Let's check if we can inspect the mailer.

        // In recent Symfony versions, we can use:
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame($subject, $email->getSubject());

        // Check if body contains rendered content (proving Twig worked)
        // Note: The template likely renders "Hello Tester" or similar.
        // Since we don't know the exact content, just checking it didn't crash is a good start,
        // but let's try to verify context usage if possible.
        // We'll assume the template renders at least something non-empty.
        $this->assertNotEmpty($email->getHtmlBody());
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testThrowsExceptionForMissingTemplate(): void
    {
        $this->expectException(LoaderError::class);

        $this->service->send(
            'fail@example.com',
            'Fail',
            'non_existent_template.html.twig'
        );
    }
}
