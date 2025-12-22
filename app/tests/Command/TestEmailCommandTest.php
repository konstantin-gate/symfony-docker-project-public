<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TestEmailCommand;
use App\Service\EmailService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class TestEmailCommandTest extends TestCase
{
    private EmailService&MockObject $emailService;
    private TestEmailCommand $command;

    protected function setUp(): void
    {
        $this->emailService = $this->createMock(EmailService::class);
        $this->command = new TestEmailCommand($this->emailService);
    }

    public function testExecuteSendsEmailSuccessfully(): void
    {
        $to = 'user@test.com';

        $this->emailService->expects($this->once())
            ->method('send')
            ->with(
                $to,
                'Test Email from CLI',
                'email/greeting.html.twig',
                $this->anything()
            );

        $application = new Application();
        $application->addCommand($this->command);
        $command = $application->find('greeting:test-email');

        $commandTester = new CommandTester($command);
        $commandTester->execute(['to' => $to]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Email sent successfully', $commandTester->getDisplay());
    }

    public function testExecuteHandlesExceptionsGracefully(): void
    {
        $this->emailService->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('Transport error'));

        $application = new Application();
        $application->addCommand($this->command);
        $command = $application->find('greeting:test-email');

        $commandTester = new CommandTester($command);
        $commandTester->execute(['to' => 'fail@example.com']);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Error: Transport error', $commandTester->getDisplay());
    }
}
