<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\Greeting\Message\BulkEmailDispatchMessage;
use App\Greeting\Service\GreetingMailService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Testovací třída pro GreetingMailService, která ověřuje správnou funkčnost služby pro hromadné odesílání e-mailů.
 */
class GreetingMailServiceTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;
    private LoggerInterface&MockObject $logger;
    private GreetingMailService $service;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new GreetingMailService(
            $this->bus,
            $this->logger
        );
    }

    /**
     * Testuje chování služby při odesílání zpráv na prázdný seznam kontaktů.
     * Ověřuje, že není odeslána žádná zpráva a není logováno.
     *
     * @throws ExceptionInterface
     */
    public function testSendToEmptyList(): void
    {
        $this->bus->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('info');

        $result = $this->service->sendGreetings([], 'Subject', 'Body');
        $this->assertSame(0, $result);
    }

    /**
     * Testuje odeslání hromadné zprávy s platnými daty.
     * Ověřuje, že je zpráva správně odeslána a logováno.
     *
     * @throws ExceptionInterface
     */
    public function testDispatchesBulkMessageWithValidData(): void
    {
        $contactIds = ['uuid-1', 'uuid-2'];
        $subject = 'Happy New Year';
        $body = 'Test Body';

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn ($message) => $message instanceof BulkEmailDispatchMessage
                    && $message->contactIds === $contactIds
                    && $message->subject === $subject
                    && $message->body === $body))
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Dispatched bulk email job'), $this->arrayHasKey('count'));

        $result = $this->service->sendGreetings($contactIds, $subject, $body);
        $this->assertSame(2, $result);
    }

    /**
     * Testuje zpracování smíšených typů ID a jejich normalizaci na řetězce.
     * Ověřuje, že jsou ID správně normalizována a zpráva odeslána.
     *
     * @throws ExceptionInterface
     */
    public function testHandlesMixedIdTypesAndNormalizesToStrings(): void
    {
        $stringId = 'string-uuid';
        $uuidObj = Uuid::v4();

        $contactIds = [$stringId, $uuidObj];
        $expectedIds = [$stringId, (string) $uuidObj];

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn (BulkEmailDispatchMessage $message) => $message->contactIds === $expectedIds))
            ->willReturn(new Envelope(new \stdClass()));

        $result = $this->service->sendGreetings($contactIds, 'Subject', 'Body');
        $this->assertSame(2, $result);
    }
}
