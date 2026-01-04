<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Service\RateProvider;

use App\MultiCurrencyWallet\Service\RateProvider\CurrencyFreaksProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Testy pro poskytovatele kurzů CurrencyFreaksProvider.
 *
 * Ověřuje správnost volání externího API a transformaci dat do formátu RateDto.
 */
class CurrencyFreaksProviderTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;

    private CurrencyFreaksProvider $provider;

    /**
     * Inicializace testovacího prostředí před každým testem.
     */
    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->provider = new CurrencyFreaksProvider($this->httpClient, 'test-api-key');
    }

    /**
     * Testuje úspěšné stažení a zpracování kurzů z API.
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testFetchRatesSuccess(): void
    {
        $responseData = [
            'base' => 'USD',
            'rates' => [
                'EUR' => '0.92',
                'CZK' => '22.50',
            ],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($responseData);

        $this->httpClient->method('request')
            ->with('GET', 'https://api.currencyfreaks.com/v2.0/rates/latest')
            ->willReturn($response);

        $rates = $this->provider->fetchRates();

        // Ověření počtu a obsahu výsledných objektů
        $this->assertCount(2, $rates);

        $this->assertSame('USD', $rates[0]->sourceCurrency);
        $this->assertSame('EUR', $rates[0]->targetCurrency);
        $this->assertSame('0.92', $rates[0]->rate);
    }

    /**
     * Testuje reakci poskytovatele na nevalidní strukturu odpovědi z API.
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testFetchRatesThrowsExceptionOnInvalidResponse(): void
    {
        $responseData = ['error' => 'Some error']; // Chybějící klíč 'rates'

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($responseData);

        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API request failed');

        $this->provider->fetchRates();
    }
}
