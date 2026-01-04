<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller\Api;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\BalanceRepository;
use App\MultiCurrencyWallet\Service\CurrencyConverter;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API kontroler pro výpočet celkové hodnoty peněženky.
 * Sečte všechny zůstatky v peněžence a převede je na zvolenou cílovou měnu.
 */
class CalculateTotalController extends AbstractController
{
    public function __construct(
        private readonly BalanceRepository $balanceRepository,
        private readonly CurrencyConverter $currencyConverter,
    ) {
    }

    /**
     * Vypočítá celkovou hodnotu peněženky v požadované měně.
     *
     * @throws \JsonException
     * @throws MoneyMismatchException
     * @throws UnknownCurrencyException
     */
    #[Route('/api/multi-currency-wallet/calculate-total', name: 'api_multi_currency_wallet_calculate_total', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $targetCurrencyCode = $data['targetCurrency'] ?? null;

        if (!$targetCurrencyCode || !CurrencyEnum::tryFrom($targetCurrencyCode)) {
            return $this->json(['error' => 'Invalid target currency'], 400);
        }

        $targetCurrency = CurrencyEnum::from($targetCurrencyCode);

        // 1. Načíst všechny zůstatky z DB
        $balances = $this->balanceRepository->findAll();

        // 2. Inicializovat sumu 0 v cílové měně
        $total = Money::zero($targetCurrency->toBrickCurrency());

        // 3. Projít a sečíst
        foreach ($balances as $balance) {
            // Konverze Money objektu z Balance entity
            $converted = $this->currencyConverter->convert(
                $balance->getMoney(),
                $targetCurrency
            );
            $total = $total->plus($converted);
        }

        return $this->json([
            'total' => (string) $total->getAmount(),
            'currency' => $targetCurrency->value,
        ]);
    }
}