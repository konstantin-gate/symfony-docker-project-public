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

/**
 * Testovací třída pro EmailSequenceService.
 * Otestuje funkčnost poslání sekvence e-mailů s časovým odstupem.
 */
class EmailSequenceServiceTest extends TestCase
{
    private MessageBusInterface&MockObject $bus;
    private EmailSequenceService $service;

    /**
     * Inicializace testovací třídy.
     * Vytvoří mock MessageBus a nastaví EmailSequenceService s pevnou prodlevou 10 sekund.
     */
    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        // Pevná prodleva 10 sekund
        $this->service = new EmailSequenceService($this->bus, 10);
    }

    /**
     * Testuje, že služba nic neudělá při prázdném seznamu požadavků.
     *
     * @throws ExceptionInterface
     */
    public function testDoesNothingOnEmptyRequestList(): void
    {
        // Očekáváme, že dispatch nebude volán
        $this->bus->expects($this->never())->method('dispatch');

        $this->service->sendSequence([]);
    }

    /**
     * Testuje, že služba správně odesílá zprávu z požadavku.
     *
     * @throws ExceptionInterface
     */
    public function testDispatchesMessageFromRequest(): void
    {
        // Vytvoření testovacího požadavku
        $request = new EmailRequest(
            'test@example.com',
            'Subject',
            'template.html.twig',
            ['key' => 'value']
        );

        // Očekáváme jeden volání dispatch s instancí SendEmailMessage
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(SendEmailMessage::class)
            )
            ->willReturn(new Envelope(new \stdClass())); // MessageBus vrací Envelope

        $this->service->sendSequence([$request]);
    }

    /**
     * Testuje, že služba správně vytváří SendEmailMessage z požadavku.
     * Ověřuje, že všechny atributy z EmailRequest jsou správně přeneseny do zprávy.
     *
     * @throws ExceptionInterface
     */
    public function testCreatesCorrectSendEmailMessageFromRequest(): void
    {
        // Vytvoření testovacího požadavku s konkrétními daty
        $request = new EmailRequest(
            'recipient@example.com',
            'Test Subject',
            'email/custom.html.twig',
            ['name' => 'John']
        );

        // Očekáváme jeden volání dispatch s zprávou obsahující správné atributy
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
     * Testuje, že služba správně vypočítává postupné zpoždění mezi zprávami.
     * Ověřuje, že první zpráva nemá zpoždění a každá další má zpoždění o 10s více než předchozí.
     *
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

                // První volání: Žádné zpoždění (Index 0 * 10s = 0)
                if ($callIndex === 1) {
                    $this->assertEmpty($stamps, 'První zpráva by neměla mít žádné zpoždění');
                }

                // Druhé volání: 10s zpoždění (Index 1 * 10s = 10000ms)
                if ($callIndex === 2) {
                    $this->assertNotEmpty($stamps);
                    $this->assertInstanceOf(\Symfony\Component\Messenger\Stamp\DelayStamp::class, $stamps[0]);
                    $this->assertEquals(10000, $stamps[0]->getDelay());
                }

                // Třetí volání: 20s zpoždění (Index 2 * 10s = 20000ms)
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
     * Testuje, že služba správně zpracovává velkou sekvenci zpráv s korektním zpožděním.
     * Ověřuje, že každá zpráva má správné zpoždění podle svého indexu.
     *
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

                $expectedDelay = $index * 10 * 1000; // 10s zpoždění z setUp

                if ($index === 0) {
                    $this->assertEmpty($stamps, "Zpráva na indexu {$index} by neměla mít žádné zpoždění");
                } else {
                    $this->assertCount(1, $stamps, "Zpráva na indexu {$index} by měla mít právě jeden timestamp");
                    $this->assertInstanceOf(\Symfony\Component\Messenger\Stamp\DelayStamp::class, $stamps[0]);
                    $this->assertEquals($expectedDelay, $stamps[0]->getDelay(), "Nesprávné zpoždění pro index {$index}");
                }

                return new Envelope($message);
            });

        $this->service->sendSequence($requests);
    }

    /**
     * Testuje, že služba respektuje nastavenou hodnotu zpoždění.
     * Ověřuje, že zpoždění mezi zprávami odpovídá konfiguraci služby.
     *
     * @throws ExceptionInterface
     */
    public function testRespectsConfiguredDelayValue(): void
    {
        // Vytvoření služby s vlastní hodnotou zpoždění 5 sekund
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

                // Druhá zpráva by měla mít zpoždění 5000ms (5s)
                if ($index === 1) {
                    $this->assertEquals($customDelay * 1000, $stamps[0]->getDelay());
                }

                return new Envelope($message);
            });

        $service->sendSequence($requests);
    }

    /**
     * Testuje, že služba správně propaguje výjimky z Messenger.
     * Ověřuje, že výjimky z dispatch jsou správně předány volajícímu.
     *
     * @throws ExceptionInterface
     */
    public function testPropagatesMessengerException(): void
    {
        // Vytvoření testovacího požadavku
        $request = new EmailRequest('test@example.com', 'Sub', 'template.html.twig');

        // Konfigurace mocku pro vyhození výjimky
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new TransportException('Connection failed'));

        // Očekáváme vyhození TransportException s konkrétní zprávou
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Connection failed');

        $this->service->sendSequence([$request]);
    }

    /**
     * Testuje, že služba nepřidává DelayStamp při nulovém zpoždění.
     * Ověřuje, že při zpoždění 0 sekund nejsou přidávány žádné timestampy.
     *
     * @throws ExceptionInterface
     */
    public function testNoDelayStampWhenDelayIsZero(): void
    {
        // Vytvoření služby s nulovým zpožděním
        $service = new EmailSequenceService($this->bus, 0);

        // Očekáváme tři volání dispatch vždy s prázdnými timestampy
        $this->bus->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->anything(), $this->equalTo([])) // vždy prázdné timestampy
            ->willReturn(new Envelope(new \stdClass()));

        $service->sendSequence([
            new EmailRequest('1@test.com', 'S', 'T'),
            new EmailRequest('2@test.com', 'S', 'T'),
            new EmailRequest('3@test.com', 'S', 'T'),
        ]);
    }
}
