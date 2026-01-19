<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller\Api;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\WalletConfigurationRepository;
use App\MultiCurrencyWallet\Service\RateHistoryService;
use App\MultiCurrencyWallet\Service\ReferenceRateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API kontroler pro získání historických dat směnných kurzů.
 *
 * Tento kontroler slouží jako zdroj dat pro Python mikroservis (Smart Trend Forecaster),
 * který je využívá k predikci budoucího vývoje kurzů. Vrací časovou řadu kurzů
 * pro zadanou cílovou měnu vůči základní měně peněženky.
 *
 * Využívá RateHistoryService, který zajišťuje správný výpočet kurzů včetně
 * křížových kurzů přes pivot měnu (USD).
 */
class GetRateHistoryController extends AbstractController
{
    public function __construct(
        private readonly RateHistoryService $rateHistoryService,
        private readonly WalletConfigurationRepository $configurationRepository,
        private readonly ReferenceRateService $referenceRateService,
    ) {}

    /**
     * Zpracovává požadavek na získání historie směnných kurzů.
     *
     * Vrací pole záznamů s datem a kurzem pro zadanou měnu za posledních N dní.
     * Používá RateHistoryService pro správný výpočet kurzů (přímých, inverzních i křížových).
     *
     * @param Request $request HTTP požadavek s parametry:
     *                         - currency (string): Kód cílové měny (např. EUR, USD)
     *                         - days (int): Počet dní historie (výchozí 30)
     *
     * @return JsonResponse JSON odpověď s polem historie kurzů nebo chybovou zprávou
     */
    #[Route('/api/multi-currency-wallet/history', name: 'api_multi_currency_wallet_history', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $currencyCode = $request->query->get('currency', 'EUR');
            $days = (int) $request->query->get('days', 30);

            // Validace vstupních parametrů
            $targetCurrency = CurrencyEnum::tryFrom($currencyCode);
            if ($targetCurrency === null) {
                return $this->json([
                    'success' => false,
                    'error' => "Neplatný kód měny: {$currencyCode}",
                ], 400);
            }

            if ($days < 1 || $days > 365) {
                return $this->json([
                    'success' => false,
                    'error' => 'Počet dní musí být v rozmezí 1-365',
                ], 400);
            }

            // Získání základní měny z konfigurace peněženky
            $config = $this->configurationRepository->getConfiguration();
            $baseCurrency = $config->getMainCurrency();

            // Načtení historických dat pomocí RateHistoryService
            // Tato služba správně počítá křížové kurzy přes USD
            $history = $this->rateHistoryService->getHistory($baseCurrency, $targetCurrency, $days);

            return $this->json([
                'success' => true,
                'base_currency' => $baseCurrency->value,
                'base_amount' => $this->referenceRateService->getSmartAmount($baseCurrency),
                'target_currency' => $targetCurrency->value,
                'days' => $days,
                'count' => count($history),
                'history' => $history,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
