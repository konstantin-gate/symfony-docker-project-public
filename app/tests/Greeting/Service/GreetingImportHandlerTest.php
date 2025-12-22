<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Service\GreetingContactService;
use App\Greeting\Service\GreetingEmailParser;
use App\Greeting\Service\GreetingImportHandler;
use App\Greeting\Service\GreetingXmlParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GreetingImportHandlerTest extends TestCase
{
    private GreetingXmlParser&MockObject $xmlParser;
    private GreetingEmailParser&MockObject $emailParser;
    private GreetingContactService&MockObject $contactService;
    private LoggerInterface&MockObject $logger;
    private GreetingImportHandler $handler;

    protected function setUp(): void
    {
        $this->xmlParser = $this->createMock(GreetingXmlParser::class);
        $this->emailParser = $this->createMock(GreetingEmailParser::class);
        $this->contactService = $this->createMock(GreetingContactService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new GreetingImportHandler(
            $this->xmlParser,
            $this->emailParser,
            $this->contactService,
            $this->logger
        );
    }

    public function testImportSuccess(): void
    {
        $xmlContent = '<contacts><contact>test@example.com</contact></contacts>';
        $emails = ['test@example.com'];

        $this->xmlParser->expects($this->once())
            ->method('parse')
            ->with($xmlContent)
            ->willReturn($emails);

        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->with($emails, GreetingLanguage::Russian)
            ->willReturn(1);

        $result = $this->handler->handleImport($xmlContent, null);

        $this->assertEquals(1, $result);
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
        $this->assertEquals(1, $result);
    }

    public function testImportFromTextAndXml(): void
    {
        $xmlContent = '<contacts><contact>xml@test.com</contact></contacts>';
        $textContent = 'text@test.com';

        $this->xmlParser->method('parse')->willReturn(['xml@test.com']);
        $this->emailParser->method('parse')->willReturn(['text@test.com']);

        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->with($this->callback(fn ($emails) => \count($emails) === 2
                && \in_array('xml@test.com', $emails, true)
                && \in_array('text@test.com', $emails, true)
            ), GreetingLanguage::Russian)
            ->willReturn(2);

        $result = $this->handler->handleImport($xmlContent, $textContent);

        $this->assertEquals(2, $result);
    }

    public function testDeduplicatesEmailsAcrossXmlAndText(): void
    {
        $xmlContent = '<contacts><email>duplicate@test.com</email></contacts>';
        $textContent = "duplicate@test.com\nanother@test.com";

        $this->xmlParser->method('parse')->willReturn(['duplicate@test.com']);
        $this->emailParser->method('parse')->willReturn(['duplicate@test.com', 'another@test.com']);

        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->with(['duplicate@test.com', 'another@test.com'], GreetingLanguage::Russian)
            ->willReturn(2);

        $result = $this->handler->handleImport($xmlContent, $textContent);
        $this->assertEquals(2, $result);
    }

    public function testPassesCustomLanguageToContactService(): void
    {
        $xmlContent = '<contacts><email>test@example.com</email></contacts>';

        $this->xmlParser->method('parse')->willReturn(['test@example.com']);
        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->with(['test@example.com'], GreetingLanguage::English)
            ->willReturn(1);

        $result = $this->handler->handleImport($xmlContent, null, GreetingLanguage::English);
        $this->assertEquals(1, $result);
    }

    public function testReturnsZeroWhenParsersReturnEmptyArrays(): void
    {
        $this->xmlParser->method('parse')->willReturn([]);
        $this->emailParser->method('parse')->willReturn([]);

        $this->contactService->expects($this->never())->method('saveContacts');

        $result = $this->handler->handleImport('<xml/>', 'some text');
        $this->assertEquals(0, $result);
    }

    public function testImportWithNoData(): void
    {
        $this->xmlParser->expects($this->never())->method('parse');
        $this->emailParser->expects($this->never())->method('parse');
        $this->contactService->expects($this->never())->method('saveContacts');

        $result = $this->handler->handleImport(null, null);

        $this->assertEquals(0, $result);
    }

    public function testIgnoresWhitespaceOnlyContent(): void
    {
        $whitespaceXml = "   \n\t\r   ";
        $whitespaceText = "   \n\t\r   ";

        $this->xmlParser->expects($this->never())->method('parse');
        $this->emailParser->expects($this->never())->method('parse');
        $this->contactService->expects($this->never())->method('saveContacts');

        $result = $this->handler->handleImport($whitespaceXml, $whitespaceText);
        $this->assertEquals(0, $result);
    }

    public function testImportFailsOnInvalidXml(): void
    {
        $xmlContent = 'invalid xml';

        $this->xmlParser->expects($this->once())
            ->method('parse')
            ->willThrowException(new \Exception('Syntax error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('XML parsing failed'),
                $this->arrayHasKey('content_snippet')
            );

        $this->expectException(\Exception::class);

        $this->handler->handleImport($xmlContent, null);
    }

    public function testImportFailsOnInvalidTextInput(): void
    {
        $textContent = 'invalid-text-input-causing-exception';

        $this->emailParser->expects($this->once())
            ->method('parse')
            ->with($textContent)
            ->willThrowException(new \RuntimeException('Invalid format'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Text parsing failed'),
                $this->logicalNot($this->arrayHasKey('content_snippet'))
            );

        $this->expectException(\RuntimeException::class);

        $this->handler->handleImport(null, $textContent);
    }

    public function testImportFailsOnDatabaseError(): void
    {
        $xmlContent = '<xml/>';
        $this->xmlParser->method('parse')->willReturn(['test@test.com']);

        $this->contactService->expects($this->once())
            ->method('saveContacts')
            ->willThrowException(new \RuntimeException('DB Error'));

        $this->logger->expects($this->once())
            ->method('critical');

        $this->expectException(\RuntimeException::class);

        $this->handler->handleImport($xmlContent, null);
    }
}
