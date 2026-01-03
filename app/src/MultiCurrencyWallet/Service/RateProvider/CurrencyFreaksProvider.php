<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Service\RateProvider;

use App\MultiCurrencyWallet\Service\RateProvider\Dto\RateDto;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implementace poskytovatele směnných kurzů využívající CurrencyFreaks API.
 * Tento poskytovatel slouží jako záložní zdroj (backup) v případě výpadku primárního API.
 */
class CurrencyFreaksProvider implements ExchangeRateProviderInterface
{
    private const string SYMBOLS = 'EUR,CZK,RUB,JPY,BTC';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
    ) {
    }

    /**
     * Získá aktuální směnné kurzy z API currencyfreaks.com.
     *
     * @return RateDto[]
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function fetchRates(): array
    {
        $response = $this->httpClient->request('GET', 'https://api.currencyfreaks.com/v2.0/rates/latest', [
            'query' => [
                'apikey' => $this->apiKey,
                'symbols' => self::SYMBOLS,
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['rates'])) {
            throw new \RuntimeException('API request failed: Invalid response structure');
        }

        $base = $data['base'] ?? 'USD';
        $rates = [];

        foreach ($data['rates'] as $currency => $rate) {
            $rates[] = new RateDto(
                sourceCurrency: $base,
                targetCurrency: $currency,
                rate: (string) $rate
            );
        }

        return $rates;
    }

    /**
     * Vrátí název této implementace poskytovatele.
     */
    public function getName(): string
    {
        return 'currencyfreaks.com';
    }
}
