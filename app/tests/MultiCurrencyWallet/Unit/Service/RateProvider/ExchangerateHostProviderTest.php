<?php

declare(strict_types=1);

namespace App\Tests\MultiCurrencyWallet\Unit\Service\RateProvider;

use App\MultiCurrencyWallet\Service\RateProvider\ExchangerateHostProvider;
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
 * Testy pro poskytovatele směnných kurzů ExchangerateHostProvider.
 * Ověřuje správné zpracování dat z externího API a ošetření chybových stavů.
 */
class ExchangerateHostProviderTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private ExchangerateHostProvider $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->provider = new ExchangerateHostProvider($this->httpClient, 'test-api-key');
    }

    /**
     * Testuje úspěšné stažení a zpracování kurzů z API.
     * Ověřuje, že se správně transformují data z formátu API na pole RateDto.
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
            'success' => true,
            'source' => 'USD',
            'quotes' => [
                'USDEUR' => 0.92,
                'USDCZK' => 22.50,
            ],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($responseData);

        $this->httpClient->method('request')
            ->with('GET', 'http://api.exchangerate.host/live')
            ->willReturn($response);

        $rates = $this->provider->fetchRates();

        $this->assertCount(2, $rates);

        $this->assertSame('USD', $rates[0]->sourceCurrency);
        $this->assertSame('EUR', $rates[0]->targetCurrency);
        $this->assertSame('0.92', $rates[0]->rate);
    }

    /**
     * Testuje chování poskytovatele v případě, že API vrátí chybu (success: false).
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testFetchRatesThrowsExceptionOnApiFailure(): void
    {
        $responseData = [
            'success' => false,
            'error' => ['info' => 'Access Denied'],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($responseData);

        $this->httpClient->method('request')->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API request failed: Access Denied');

        $this->provider->fetchRates();
    }
}
