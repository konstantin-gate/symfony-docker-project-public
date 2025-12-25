<?php

declare(strict_types=1);

namespace App\Tests\Greeting\MessageHandler;

use App\DTO\EmailRequest;
use App\Greeting\Message\BulkEmailDispatchMessage;
use App\Greeting\MessageHandler\BulkEmailDispatchMessageHandler;
use App\Greeting\Repository\GreetingContactRepository;
use App\Service\EmailSequenceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

/**
 * Testovací třída pro BulkEmailDispatchMessageHandler.
 * Otestuje správné zpracování hromadného odesílání e-mailů v dávkách.
 */
class BulkEmailDispatchMessageHandlerTest extends TestCase
{
    /**
     * Testuje, zda handler správně zpracuje dávky kontaktů.
     * Otestuje rozdělení 150 kontaktů do dvou dávek (100 + 50) a správné volání služeb.
     *
     * @throws ExceptionInterface
     */
    public function testHandlerProcessChunksCorrectly(): void
    {
        // Nastavení mocků
        $repository = $this->createMock(GreetingContactRepository::class);
        $emailService = $this->createMock(EmailSequenceService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Generování 150 ID pro testování 2 dávek (100 + 50)
        $allIds = array_map(static fn ($i) => "id-$i", range(1, 150));

        // Mock chování Repository: vrátí e-maily pro ID
        $repository->expects($this->exactly(2))
            ->method('findEmailsByIds')
            ->willReturnCallback(fn (array $ids) => array_map(static fn ($id) => "$id@example.com", $ids));

        // Mock EmailSequenceService: očekává 2 volání (jedno na dávku)
        $emailService->expects($this->exactly(2))
            ->method('sendSequence')
            ->with($this->callback(function (array $requests) {
                // Zkontroluje, zda dostáváme objekty EmailRequest
                return \count($requests) > 0 && $requests[0] instanceof EmailRequest;
            }));

        // Mock EntityManager: očekává volání clear() dvakrát
        $entityManager->expects($this->exactly(2))
            ->method('clear');

        $handler = new BulkEmailDispatchMessageHandler(
            $repository,
            $emailService,
            $entityManager,
            $logger
        );

        $message = new BulkEmailDispatchMessage(
            contactIds: $allIds,
            subject: 'Test Subject',
            body: 'Test Body'
        );

        // Volání handleru
        $handler($message);
    }

    /**
     * Testuje správné zpracování prázdného seznamu kontaktů.
     * Ověřuje, že handler nevolá žádné služby při prázdném vstupu.
     *
     * @throws ExceptionInterface
     */
    public function testHandlesEmptyList(): void
    {
        $repository = $this->createMock(GreetingContactRepository::class);
        $emailService = $this->createMock(EmailSequenceService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $repository->expects($this->never())->method('findEmailsByIds');
        $emailService->expects($this->never())->method('sendSequence');

        $handler = new BulkEmailDispatchMessageHandler(
            $repository,
            $emailService,
            $entityManager,
            $logger
        );

        $handler(new BulkEmailDispatchMessage([], 'Sub', 'Body'));
    }
}
