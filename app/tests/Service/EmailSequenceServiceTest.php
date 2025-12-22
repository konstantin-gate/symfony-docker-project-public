<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\EmailRequest;
use App\Message\SendEmailMessage;
use App\Service\EmailSequenceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

class EmailSequenceServiceTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;
    private EmailSequenceService $service;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        // Fixed delay of 10 seconds
        $this->service = new EmailSequenceService($this->bus, 10);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testDoesNothingOnEmptyRequestList(): void
    {
        $this->bus->expects($this->never())->method('dispatch');

        $this->service->sendSequence([]);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testDispatchesMessageFromRequest(): void
    {
        $request = new EmailRequest(
            'test@example.com',
            'Subject',
            'template.html.twig',
            ['key' => 'value']
        );

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(SendEmailMessage::class)
            )
            ->willReturn(new Envelope(new \stdClass())); // MessageBus returns Envelope

        $this->service->sendSequence([$request]);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testCreatesCorrectSendEmailMessageFromRequest(): void
    {
        $request = new EmailRequest(
            'recipient@example.com',
            'Test Subject',
            'email/custom.html.twig',
            ['name' => 'John']
        );

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(fn (SendEmailMessage $message) => $message->to === $request->to
                        && $message->subject === $request->subject
                        && $message->template === $request->template
                        && $message->context === $request->context),
                $this->anything()
            )
            ->willReturn(new Envelope($request));

        $this->service->sendSequence([$request]);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testCalculatesProgressiveDelayCorrectly(): void
    {
        $requests = [
            new EmailRequest('1@test.com', 'S1', 'T1'),
            new EmailRequest('2@test.com', 'S2', 'T2'),
            new EmailRequest('3@test.com', 'S3', 'T3'),
        ];

        $callCount = 0;
        $this->bus->expects($this->exactly(3))
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) use (&$callCount) {
                ++$callCount;
                $callIndex = $callCount; // 1, 2, 3...

                // 1st call: No delay (Index 0 * 10s = 0)
                if ($callIndex === 1) {
                    $this->assertEmpty($stamps, 'First message should have no delay stamps');
                }

                // 2nd call: 10s delay (Index 1 * 10s = 10000ms)
                if ($callIndex === 2) {
                    $this->assertNotEmpty($stamps);
                    $this->assertInstanceOf(\Symfony\Component\Messenger\Stamp\DelayStamp::class, $stamps[0]);
                    $this->assertEquals(10000, $stamps[0]->getDelay());
                }

                // 3rd call: 20s delay (Index 2 * 10s = 20000ms)
                if ($callIndex === 3) {
                    $this->assertNotEmpty($stamps);
                    $this->assertInstanceOf(\Symfony\Component\Messenger\Stamp\DelayStamp::class, $stamps[0]);
                    $this->assertEquals(20000, $stamps[0]->getDelay());
                }

                return new Envelope($message);
            });

        $this->service->sendSequence($requests);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testHandlesLargeSequenceWithCorrectDelays(): void
    {
        $count = 50;
        $requests = array_map(
            fn ($i) => new EmailRequest("user{$i}@example.com", 'Sub', 'template.html.twig'),
            range(0, $count - 1)
        );

        $callCount = 0;
        $this->bus->expects($this->exactly($count))
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) use (&$callCount) {
                $index = $callCount++;

                $expectedDelay = $index * 10 * 1000; // 10s delay from setUp

                if ($index === 0) {
                    $this->assertEmpty($stamps, "Message at index {$index} should have no delay");
                } else {
                    $this->assertCount(1, $stamps, "Message at index {$index} should have exactly one stamp");
                    $this->assertInstanceOf(\Symfony\Component\Messenger\Stamp\DelayStamp::class, $stamps[0]);
                    $this->assertEquals($expectedDelay, $stamps[0]->getDelay(), "Incorrect delay for index {$index}");
                }

                return new Envelope($message);
            });

        $this->service->sendSequence($requests);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testRespectsConfiguredDelayValue(): void
    {
        $customDelay = 5;
        $service = new EmailSequenceService($this->bus, $customDelay);

        $requests = [
            new EmailRequest('1@test.com', 'S', 'T'),
            new EmailRequest('2@test.com', 'S', 'T'),
        ];

        $callCount = 0;
        $this->bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) use ($customDelay, &$callCount) {
                $index = $callCount++;

                if ($index === 1) {
                    $this->assertEquals($customDelay * 1000, $stamps[0]->getDelay());
                }

                return new Envelope($message);
            });

        $service->sendSequence($requests);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testPropagatesMessengerException(): void
    {
        $request = new EmailRequest('test@example.com', 'Sub', 'template.html.twig');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new TransportException('Connection failed'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Connection failed');

        $this->service->sendSequence([$request]);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testNoDelayStampWhenDelayIsZero(): void
    {
        $service = new EmailSequenceService($this->bus, 0);

        $this->bus->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->anything(), $this->equalTo([])) // always empty stamps
            ->willReturn(new Envelope(new \stdClass()));

        $service->sendSequence([
            new EmailRequest('1@test.com', 'S', 'T'),
            new EmailRequest('2@test.com', 'S', 'T'),
            new EmailRequest('3@test.com', 'S', 'T'),
        ]);
    }
}
