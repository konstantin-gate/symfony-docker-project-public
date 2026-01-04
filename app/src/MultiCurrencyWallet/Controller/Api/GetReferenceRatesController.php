<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller\Api;

use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use App\MultiCurrencyWallet\Service\ReferenceRateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API kontroler pro získání seznamu referenčních směnných kurzů.
 */
class GetReferenceRatesController extends AbstractController
{
    public function __construct(
        private readonly ReferenceRateService $referenceRateService,
        private readonly ExchangeRateRepository $exchangeRateRepository,
    ) {
    }

    /**
     * Zpracovává požadavek na získání seznamu referenčních směnných kurzů.
     * Vrací pole kurzů včetně vypočtených částek pro definované páry a informaci o čase poslední aktualizace.
     */
    #[Route('/api/multi-currency-wallet/reference-rates', name: 'api_multi_currency_wallet_reference_rates', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $rates = $this->referenceRateService->getReferenceRates();

            // Získáme informaci o čase poslední aktualizace kurzu (globálně)
            $latestRate = $this->exchangeRateRepository->findLatestUpdate();
            $updatedAt = $latestRate?->getFetchedAt()
                ->setTimezone(new \DateTimeZone('Europe/Prague'))
                ->format(\DateTimeInterface::ATOM);

            // Přidáme datum aktualizace ke každému záznamu, pokud tam není
            foreach ($rates as &$rate) {
                if ($rate['updated_at'] === null) {
                    $rate['updated_at'] = $updatedAt;
                }
            }
            unset($rate);

            return $this->json([
                'success' => true,
                'rates' => $rates,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
