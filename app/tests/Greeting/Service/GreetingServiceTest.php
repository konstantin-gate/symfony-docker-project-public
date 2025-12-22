<?php

declare(strict_types=1);

namespace App\Tests\Greeting\Service;

use App\Enum\Status;
use App\Greeting\Entity\GreetingContact;
use App\Greeting\Enum\GreetingLanguage;
use App\Greeting\Repository\GreetingContactRepository;
use App\Greeting\Service\GreetingService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GreetingServiceTest extends TestCase
{
    private GreetingContactRepository&MockObject $repository;
    private GreetingService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GreetingContactRepository::class);
        $this->service = new GreetingService($this->repository);
    }

    public function testReturnsEmptyArraysForEachLanguageWhenNoContactsFound(): void
    {
        // Setup mock to return empty array
        $this->repository->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        // Execute method
        $result = $this->service->getContactsGroupedByLanguage();

        // Assertions
        // Check that keys exist for all defined languages
        foreach (GreetingLanguage::cases() as $language) {
            $this->assertArrayHasKey($language->value, $result);
            $this->assertEmpty($result[$language->value]);
        }
    }

    public function testGroupsContactsByLanguageCorrectly(): void
    {
        // Create mocks for GreetingContact
        $contactEn1 = $this->createMock(GreetingContact::class);
        $contactEn1->method('getLanguage')->willReturn(GreetingLanguage::English);

        $contactEn2 = $this->createMock(GreetingContact::class);
        $contactEn2->method('getLanguage')->willReturn(GreetingLanguage::English);

        $contactCs1 = $this->createMock(GreetingContact::class);
        $contactCs1->method('getLanguage')->willReturn(GreetingLanguage::Czech);

        // Setup mock repository to return these contacts
        $this->repository->expects($this->once())
            ->method('findBy')
            ->willReturn([$contactEn1, $contactEn2, $contactCs1]);

        // Execute method
        $result = $this->service->getContactsGroupedByLanguage();

        // Assertions
        // Ensure all language keys exist
        foreach (GreetingLanguage::cases() as $language) {
            $this->assertArrayHasKey($language->value, $result);
        }

        $this->assertCount(2, $result[GreetingLanguage::English->value]);
        $this->assertCount(1, $result[GreetingLanguage::Czech->value]);
        $this->assertCount(0, $result[GreetingLanguage::Russian->value]);

        // Verify exact instances
        $this->assertSame($contactEn1, $result[GreetingLanguage::English->value][0]);
        $this->assertSame($contactEn2, $result[GreetingLanguage::English->value][1]);
        $this->assertSame($contactCs1, $result[GreetingLanguage::Czech->value][0]);
    }

    public function testFetchesOnlyActiveContactsSortedByEmail(): void
    {
        // Setup mock to expect specific criteria and ordering
        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(
                ['status' => Status::Active],
                ['email' => 'ASC']
            )
            ->willReturn([]);

        // Execute method
        $this->service->getContactsGroupedByLanguage();
    }

    public function testContactsAreSortedByEmailWithinEachLanguageGroup(): void
    {
        $contactEnA = $this->createMock(GreetingContact::class);
        $contactEnA->method('getLanguage')->willReturn(GreetingLanguage::English);
        $contactEnA->method('getEmail')->willReturn('alice@example.com');

        $contactEnC = $this->createMock(GreetingContact::class);
        $contactEnC->method('getLanguage')->willReturn(GreetingLanguage::English);
        $contactEnC->method('getEmail')->willReturn('charlie@example.com');

        $contactEnB = $this->createMock(GreetingContact::class);
        $contactEnB->method('getLanguage')->willReturn(GreetingLanguage::English);
        $contactEnB->method('getEmail')->willReturn('bob@example.com');

        // Important: return in wrong order to test service-level sorting or reliance on repository
        // If the service doesn't sort itself, and we mock repository to return out of order, it will fail
        // which documents that we EXPECT the service to either sort or we need to be aware of repository dependency.
        $this->repository->method('findBy')->willReturn([$contactEnC, $contactEnA, $contactEnB]);

        $result = $this->service->getContactsGroupedByLanguage();

        $englishGroup = $result[GreetingLanguage::English->value];

        // Should be sorted: alice → bob → charlie
        $this->assertCount(3, $englishGroup);
        $this->assertSame('alice@example.com', $englishGroup[0]->getEmail());
        $this->assertSame('bob@example.com', $englishGroup[1]->getEmail());
        $this->assertSame('charlie@example.com', $englishGroup[2]->getEmail());
    }
}
