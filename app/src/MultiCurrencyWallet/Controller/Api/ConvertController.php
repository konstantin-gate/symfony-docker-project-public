<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller\Api;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use App\MultiCurrencyWallet\Service\CurrencyConverter;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API kontroler pro převod měn.
 * Využívá službu CurrencyConverter pro výpočet částky v cílové měně na základě aktuálních kurzů.
 */
class ConvertController extends AbstractController
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
        private readonly ExchangeRateRepository $exchangeRateRepository,
    ) {
    }

    /**
     * Zpracovává požadavek na převod měn.
     * Přijímá zdrojovou a cílovou měnu spolu s částkou, provádí výpočet pomocí služby CurrencyConverter
     * a vrací výsledek včetně informace o čase poslední aktualizace kurzů.
     */
    #[Route('/api/multi-currency-wallet/convert', name: 'api_multi_currency_wallet_convert', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            $amountStr = (string) ($data['amount'] ?? '0');
            $from = CurrencyEnum::tryFrom($data['from'] ?? '');
            $to = CurrencyEnum::tryFrom($data['to'] ?? '');

            if (!$from || !$to) {
                return $this->json(['error' => 'Invalid currency'], 400);
            }

            if (!is_numeric($amountStr) || (float) $amountStr < 0) {
                return $this->json(['error' => 'Invalid amount'], 400);
            }

            $money = Money::of($amountStr, $from->toBrickCurrency());
            $result = $this->currencyConverter->convert($money, $to);

            // Získáme informaci o čase poslední aktualizace kurzu
            $latestRate = $this->exchangeRateRepository->findLatestUpdate();
            $updatedAt = $latestRate?->getFetchedAt()
                ->setTimezone(new \DateTimeZone('Europe/Prague'))
                ->format(\DateTimeInterface::ATOM);

            return $this->json([
                'success' => true,
                'amount' => (string) $result->getAmount(),
                'currency' => $to->value,
                'rate' => (string) $result->getAmount()->dividedBy($money->getAmount(), 12, RoundingMode::HALF_UP),
                'updatedAt' => $updatedAt,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
