<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller\Api;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\WalletConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API kontroler pro uložení nastavení peněženky.
 */
class UpdateSettingsController extends AbstractController
{
    public function __construct(
        private readonly WalletConfigurationRepository $configurationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Uloží změny v globálním nastavení modulu.
     */
    #[Route('/api/multi-currency-wallet/settings', name: 'api_multi_currency_wallet_update_settings', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $config = $this->configurationRepository->getConfiguration();

            if (isset($data['mainCurrency'])) {
                $currency = CurrencyEnum::tryFrom($data['mainCurrency']);

                if ($currency) {
                    $config->setMainCurrency($currency);
                }
            }

            if (isset($data['autoUpdateEnabled'])) {
                $config->setAutoUpdateEnabled((bool) $data['autoUpdateEnabled']);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'mainCurrency' => $config->getMainCurrency()->value,
                'autoUpdateEnabled' => $config->isAutoUpdateEnabled(),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
