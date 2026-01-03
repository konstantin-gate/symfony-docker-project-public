<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller\Api;

use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Repository\BalanceRepository;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class UpdateBalanceController extends AbstractController
{
    public function __construct(
        private readonly BalanceRepository $balanceRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * API endpoint pro aktualizaci zůstatku konkrétní měny v peněžence.
     * Přijímá JSON s kódem měny a novou částkou, provádí validaci a ukládá změnu do databáze.
     *
     * @throws UnknownCurrencyException pokud kód měny není rozpoznán knihovnou brick/money
     * @throws \JsonException           pokud dojde k chybě při dekódování JSON požadavku
     */
    #[Route('/api/multi-currency-wallet/update-balance', name: 'api_multi_currency_wallet_update_balance', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $currencyCode = $data['currency'] ?? null;
        $amount = $data['amount'] ?? null;

        if (!$currencyCode || !CurrencyEnum::tryFrom($currencyCode)) {
            return $this->json(['error' => 'Invalid currency'], 400);
        }

        if (!is_numeric($amount) || (float) $amount < 0) {
            return $this->json(['error' => 'Invalid amount'], 400);
        }

        $currency = CurrencyEnum::from($currencyCode);
        $balance = $this->balanceRepository->findOneBy(['currency' => $currency]);

        if (!$balance) {
            return $this->json(['error' => 'Balance record not found'], 404);
        }

        // Aktualizujeme hodnotu. Ukládáme jako řetězec pro přesnost.
        $balance->setMoney(
            Money::of($amount, $balance->getMoney()->getCurrency())
        );

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'currency' => $currency->value,
            'amount' => $balance->getAmount(),
        ]);
    }
}
