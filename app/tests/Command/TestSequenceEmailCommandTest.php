<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TestSequenceEmailCommand;
use App\DTO\EmailRequest;
use App\Service\EmailSequenceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class TestSequenceEmailCommandTest extends TestCase
{
    private EmailSequenceService&MockObject $emailSequenceService;
    private TestSequenceEmailCommand $command;

    protected function setUp(): void
    {
        $this->emailSequenceService = $this->createMock(EmailSequenceService::class);
        $this->command = new TestSequenceEmailCommand($this->emailSequenceService);
    }

    public function testExecuteDispatchesSequenceSuccessfully(): void
    {
        $this->emailSequenceService->expects($this->once())
            ->method('sendSequence')
            ->with($this->callback(function (array $requests) {
                if (\count($requests) !== 3) {
                    return false;
                }

                return array_all($requests, static fn ($request) => $request instanceof EmailRequest);
            }));

        $application = new Application();
        $application->addCommand($this->command);
        $command = $application->find('greeting:test-sequence-email');

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Messages dispatched', $commandTester->getDisplay());
    }

    public function testExecuteHandlesExceptionsGracefully(): void
    {
        $this->emailSequenceService->expects($this->once())
            ->method('sendSequence')
            ->willThrowException(new \Exception('Queue error'));

        $application = new Application();
        $application->addCommand($this->command);
        $command = $application->find('greeting:test-sequence-email');

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Error: Queue error', $commandTester->getDisplay());
    }
}
