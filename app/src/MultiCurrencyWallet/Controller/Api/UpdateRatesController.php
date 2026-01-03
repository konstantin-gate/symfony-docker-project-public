<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller\Api;

use App\MultiCurrencyWallet\Service\RateUpdateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API kontroler pro aktualizaci směnných kurzů.
 * Poskytuje endpoint, který spouští proces synchronizace kurzů s externími poskytovateli přes RateUpdateService.
 */
class UpdateRatesController extends AbstractController
{
    public function __construct(
        private readonly RateUpdateService $rateUpdateService,
    ) {
    }

    #[Route('/api/multi-currency-wallet/update-rates', name: 'api_multi_currency_wallet_update_rates', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        try {
            $providerName = $this->rateUpdateService->updateRates();

            return $this->json([
                'success' => true,
                'provider' => $providerName,
                'skipped' => 'skipped' === $providerName,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
