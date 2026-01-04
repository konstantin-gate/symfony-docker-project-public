<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller\Api;

use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API kontroler pro získání seznamu dostupných dat pro historii kurzů.
 */
class AvailableDatesController extends AbstractController
{
    public function __construct(
        private readonly ExchangeRateRepository $exchangeRateRepository,
    ) {
    }

    /**
     * Zpracovává požadavek na získání seznamu dostupných dat pro historii kurzů.
     * Provádí inteligentní filtraci dat, aby seznam zůstal přehledný i po delší době provozu:
     * - Posledních 14 dní: Zobrazuje každý den (plná detailnost za 2 týdny).
     * - Poslední 3 měsíce: Zobrazuje pouze pondělky (týdenní dynamika).
     * - Starší záznamy: Pouze 1. den v měsíci (měsíční dynamika).
     * - Vždy zahrnuje nejaktuálnější dostupný záznam.
     */
    #[Route('/api/multi-currency-wallet/available-dates', name: 'api_multi_currency_wallet_available_dates', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $allDates = $this->exchangeRateRepository->getAvailableUpdateDates();

            if (empty($allDates)) {
                return $this->json(['success' => true, 'dates' => []]);
            }

            $pragueTz = new \DateTimeZone('Europe/Prague');
            $now = new \DateTimeImmutable('now', $pragueTz);

            $twoWeeksAgo = $now->modify('-14 days')->setTime(0, 0);
            $threeMonthsAgo = $now->modify('-3 months')->setTime(0, 0);

            $filteredDates = [];

            foreach ($allDates as $dateStr) {
                $date = new \DateTimeImmutable($dateStr, $pragueTz);

                // 1. Posledních 14 dní - bereme vše
                if ($date >= $twoWeeksAgo) {
                    $filteredDates[] = $dateStr;
                    continue;
                }

                // 2. Poslední 3 měsíce - bereme jen pondělky
                if ($date >= $threeMonthsAgo) {
                    if ($date->format('N') === '1') { // 1 = Pondělí
                        $filteredDates[] = $dateStr;
                    }
                    continue;
                }

                // 3. Starší než 3 měsíce - bereme jen 1. v měsíci
                if ($date->format('j') === '1') {
                    $filteredDates[] = $dateStr;
                }
            }

            // Ujistíme se, že nejnovější datum je vždy v seznamu (pokud tam už není)
            if (!\in_array($allDates[0], $filteredDates, true)) {
                array_unshift($filteredDates, $allDates[0]);
            }

            return $this->json([
                'success' => true,
                'dates' => array_values(array_unique($filteredDates)),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
