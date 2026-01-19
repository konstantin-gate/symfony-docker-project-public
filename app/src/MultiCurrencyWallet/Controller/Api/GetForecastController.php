<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller\Api;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * Proxy API kontroler pro získání prognózy směnných kurzů z Python mikroservisu.
 *
 * Tento kontroler slouží jako mezivrstva mezi React frontendem a FastAPI backendem
 * pro predikci kurzů měn. Zajišťuje:
 * - Validaci vstupních parametrů (kód měny)
 * - Centralizované logování všech požadavků a chyb
 * - Jednotné zpracování chyb s přeloženými zprávami
 * - Timeout ochranu pro požadavky na Python službu
 *
 * URL Python mikroservisu se konfiguruje přes proměnnou prostředí PYTHON_SERVICE_URL.
 *
 * @see /api/multi-currency-wallet/forecast/{currency}
 */
class GetForecastController extends AbstractController
{
    /**
     * Konstruktor kontroleru.
     *
     * Injektuje závislosti potřebné pro komunikaci s Python mikroservisem
     * a logování operací.
     *
     * @param HttpClientInterface $httpClient Symfony HTTP klient pro odesílání požadavků
     * @param LoggerInterface $logger Logger pro záznam událostí a chyb
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Získá prognózu směnného kurzu pro zadanou měnu.
     *
     * Metoda provádí následující kroky:
     * 1. Validuje, zda je zadaný kód měny platný (existuje v CurrencyEnum)
     * 2. Sestavuje URL pro Python mikroservis z proměnné prostředí
     * 3. Odesílá GET požadavek na FastAPI endpoint s timeoutem 10 sekund
     * 4. Parsuje JSON odpověď a vrací ji klientovi
     *
     * Podporované měny: EUR, USD, GBP, BTC, ETH a další dle CurrencyEnum.
     * CZK není podporována, protože je vždy základní měnou.
     *
     * @param string $currency Kód cílové měny ve formátu ISO 4217 (např. EUR, USD)
     *
     * @return JsonResponse JSON odpověď obsahující:
     *                      - Při úspěchu: { currency, generated_at, forecast: [...] }
     *                      - Při chybě: { success: false, error: "popis chyby" }
     *
     * @throws \RuntimeException Pokud se nepodaří parsovat JSON odpověď z Python služby
     */
    #[Route(
        '/api/multi-currency-wallet/forecast/{currency}',
        name: 'api_multi_currency_wallet_forecast',
        methods: ['GET']
    )]
    public function __invoke(string $currency): JsonResponse
    {
        try {
            // Validace měny pomocí CurrencyEnum
            $targetCurrency = CurrencyEnum::tryFrom($currency);

            if ($targetCurrency === null) {
                $this->logger->warning('Neplatný kód měny pro prognózu', [
                    'currency' => $currency,
                ]);

                return $this->json([
                    'success' => false,
                    'error' => "Neplatný kód měny: {$currency}",
                ], 400);
            }

            // CZK nelze prognózovat (je to základní měna)
            if ($targetCurrency === CurrencyEnum::CZK) {
                return $this->json([
                    'success' => false,
                    'error' => 'Prognózu nelze vytvořit pro základní měnu CZK',
                ], 400);
            }

            // URL Python mikroservisu z proměnné prostředí
            $pythonServiceUrl = $_ENV['PYTHON_SERVICE_URL'] ?? 'http://fastapi:8000';
            $url = "{$pythonServiceUrl}/wallet/analytics/forecast/{$currency}";

            $this->logger->info('Požadavek na prognózu z Python mikroservisu', [
                'currency' => $currency,
                'url' => $url,
            ]);

            // Odeslání požadavku na Python FastAPI s timeoutem
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            // Kontrola HTTP status kódu
            if ($statusCode !== 200) {
                $this->logger->error('Python mikroservis vrátil chybu', [
                    'status_code' => $statusCode,
                    'response' => $content,
                    'currency' => $currency,
                ]);

                return $this->json([
                    'success' => false,
                    'error' => 'Služba prognózy vrátila chybu',
                ], $statusCode >= 500 ? 502 : $statusCode);
            }

            // Parsování JSON odpovědi
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Nepodařilo se parsovat JSON z Python služby', [
                    'json_error' => json_last_error_msg(),
                    'content' => substr($content, 0, 500),
                ]);

                throw new \RuntimeException('Nepodařilo se parsovat odpověď ze služby prognózy');
            }

            $this->logger->info('Prognóza úspěšně získána', [
                'currency' => $currency,
                'forecast_points' => count($data['forecast'] ?? []),
                'generated_at' => $data['generated_at'] ?? null,
            ]);

            // Vrácení úspěšné odpovědi
            return $this->json($data);
        } catch (TransportExceptionInterface $e) {
            // Chyba sítě nebo timeout
            $this->logger->error('Nepodařilo se připojit k Python mikroservisu', [
                'error' => $e->getMessage(),
                'currency' => $currency,
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Služba prognózy není momentálně dostupná',
            ], 503);
        } catch (\Exception $e) {
            // Neočekávaná chyba
            $this->logger->error('Neočekávaná chyba v proxy kontroleru prognózy', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'currency' => $currency,
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Interní chyba serveru',
            ], 500);
        }
    }
}
