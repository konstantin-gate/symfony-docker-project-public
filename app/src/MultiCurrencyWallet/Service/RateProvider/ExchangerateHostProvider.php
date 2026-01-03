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
 * Implementace poskytovatele směnných kurzů využívající Exchangerate.host API.
 * Tento poskytovatel slouží jako primární zdroj dat pro aktualizaci kurzů.
 */
class ExchangerateHostProvider implements ExchangeRateProviderInterface
{
    private const string CURRENCIES = 'EUR,CZK,RUB,JPY,BTC';
    private const string SOURCE = 'USD';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
    ) {
    }

    /**
     * Získá aktuální směnné kurzy z API exchangerate.host.
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
        $response = $this->httpClient->request('GET', 'http://api.exchangerate.host/live', [
            'query' => [
                'access_key' => $this->apiKey,
                'currencies' => self::CURRENCIES,
                'source' => self::SOURCE,
                'format' => 1,
            ],
        ]);

        $data = $response->toArray();

        if (empty($data['success'])) {
            throw new \RuntimeException('API request failed: ' . ($data['error']['info'] ?? 'Unknown error'));
        }

        $rates = [];

        foreach ($data['quotes'] as $pair => $rate) {
            // Pár je např. "USDEUR", "USDBTC". Protože zdroj je USD, stačí odstranit prefix "USD".
            // Pro jistotu ověříme, zda řetězec skutečně začíná na USD.
            if (str_starts_with($pair, self::SOURCE)) {
                $targetCurrency = substr($pair, \strlen(self::SOURCE));
            } else {
                // Přeskočíme neplatné páry nebo páry s jinou základní měnou
                continue;
            }

            // Převedeme float na string pro zachování přesnosti před vytvořením DTO.
            // Poznámka: Pro velmi malá čísla (např. BTC) může standardní přetypování vytvořit vědeckou notaci (např. 1.11e-5).
            // Brick\Math\BigDecimal podporuje vědeckou notaci, takže je tento postup bezpečný.
            $rateStr = (string) $rate;

            $rates[] = new RateDto(
                sourceCurrency: self::SOURCE,
                targetCurrency: $targetCurrency,
                rate: $rateStr
            );
        }

        return $rates;
    }

    /**
     * Vrátí název této implementace poskytovatele.
     */
    public function getName(): string
    {
        return 'exchangerate.host';
    }
}
