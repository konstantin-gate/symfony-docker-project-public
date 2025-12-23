<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\Greeting\DTO\GreetingImportResult;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Service\GreetingContactService;
use App\Greeting\Service\GreetingEmailParser;
use App\Greeting\Service\GreetingImportHandler;
use App\Greeting\Service\GreetingXmlParser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GreetingImportHandlerTest extends TestCase
{
    private GreetingXmlParser&MockObject $xmlParser;
    private GreetingEmailParser&MockObject $emailParser;
    private GreetingContactService&MockObject $contactService;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private GreetingImportHandler $handler;

    protected function setUp(): void
    {
        $this->xmlParser = $this->createMock(GreetingXmlParser::class);
        $this->emailParser = $this->createMock(GreetingEmailParser::class);
        $this->contactService = $this->createMock(GreetingContactService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new GreetingImportHandler(
            $this->xmlParser,
            $this->emailParser,
            $this->contactService,
            $this->entityManager,
            $this->logger
        );
    }

    /**
     * @param string[] $emails
     *
     * @return \Generator<string>
     */
    private function mockXmlGenerator(array $emails): \Generator
    {
        foreach ($emails as $email) {
            yield $email;
        }
    }

    public function testImportSuccess(): void
    {
        $xmlFile = '/tmp/test.xml';
        $emails = ['test@example.com'];

        $this->xmlParser->expects($this->once())
            ->method('parse')
            ->with($xmlFile)
            ->willReturn($this->mockXmlGenerator($emails));

        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->with($emails, GreetingLanguage::Russian)
            ->willReturn(1);

        $this->entityManager->expects($this->once())->method('clear');

        $result = $this->handler->handleImport($xmlFile, null);

        $this->assertInstanceOf(GreetingImportResult::class, $result);
        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->count);
        $this->assertNull($result->errorKey);
    }

    public function testImportFromTextOnly(): void
    {
        $textContent = 'text@test.com';
        $emails = ['text@test.com'];

        $this->emailParser->expects($this->once())
            ->method('parse')
            ->with($textContent)
            ->willReturn($emails);

        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->with($emails, GreetingLanguage::Russian)
            ->willReturn(1);

        $result = $this->handler->handleImport(null, $textContent);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->count);
    }

    public function testImportFromTextAndXml(): void
    {
        $xmlFile = '/tmp/test.xml';
        $textContent = 'text@test.com';

        $this->xmlParser->method('parse')->willReturn($this->mockXmlGenerator(['xml@test.com']));
        $this->emailParser->method('parse')->willReturn(['text@test.com']);

        $this->contactService->expects($this->exactly(2))
            ->method('saveContacts')
            ->willReturnOnConsecutiveCalls(1, 1);

        $this->entityManager->expects($this->once())->method('clear');

        $result = $this->handler->handleImport($xmlFile, $textContent);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(2, $result->count);
    }

    public function testPassesCustomLanguageToContactService(): void
    {
        $xmlFile = '/tmp/test.xml';

        $this->xmlParser->method('parse')->willReturn($this->mockXmlGenerator(['test@example.com']));
        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->with(['test@example.com'], GreetingLanguage::English)
            ->willReturn(1);

        $this->entityManager->expects($this->once())->method('clear');

        $result = $this->handler->handleImport($xmlFile, null, GreetingLanguage::English);
        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->count);
    }

    public function testReturnsSuccessZeroWhenParsersReturnEmpty(): void
    {
        $this->xmlParser->method('parse')->willReturn($this->mockXmlGenerator([]));
        $this->emailParser->method('parse')->willReturn([]);

        $this->contactService->expects($this->never())->method('saveContacts');

        $result = $this->handler->handleImport('/tmp/empty.xml', 'some text');

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(0, $result->count);
    }

    public function testImportWithNoDataReturnsError(): void
    {
        $this->xmlParser->expects($this->never())->method('parse');
        $this->emailParser->expects($this->never())->method('parse');
        $this->contactService->expects($this->never())->method('saveContacts');

        $result = $this->handler->handleImport(null, null);

        $this->assertFalse($result->isSuccess);
        $this->assertEquals('import.error_no_data', $result->errorKey);
    }

    public function testImportReturnsErrorOnInvalidXml(): void
    {
        $xmlFile = '/tmp/invalid.xml';

        $this->xmlParser->expects($this->once())
            ->method('parse')
            ->willThrowException(new \RuntimeException('Syntax error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('XML parsing/saving failed'),
                $this->arrayHasKey('file')
            );

        $result = $this->handler->handleImport($xmlFile, null);

        $this->assertFalse($result->isSuccess);
        $this->assertEquals('import.error_xml_parsing', $result->errorKey);
    }

    public function testBatchProcessingCallsSaveContactsMultipleTimes(): void
    {
        $xmlFile = '/tmp/large.xml';

        // Generate 1001 emails (batch size is 500)
        $emails = [];
        for ($i = 0; $i < 1001; ++$i) {
            $emails[] = "user{$i}@example.com";
        }

        $this->xmlParser->method('parse')->willReturn($this->mockXmlGenerator($emails));

        $this->contactService->expects($this->exactly(3))
            ->method('saveContacts')
            ->willReturnOnConsecutiveCalls(500, 500, 1);

        $this->entityManager->expects($this->exactly(3))->method('clear');

        $result = $this->handler->handleImport($xmlFile, null);
        $this->assertEquals(1001, $result->count);
    }
}