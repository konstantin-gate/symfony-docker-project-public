<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller;

use App\MultiCurrencyWallet\Entity\Balance;
use App\MultiCurrencyWallet\Repository\BalanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class MultiCurrencyWalletController extends AbstractController
{
    public function __construct(
        private readonly BalanceRepository $balanceRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/multi-currency-wallet/{path}', name: 'multi_currency_wallet_dashboard_default', requirements: ['path' => '.*'], defaults: ['_locale' => 'cs', 'path' => ''], methods: ['GET'])]
    #[Route('/{_locale}/multi-currency-wallet/{path}', name: 'multi_currency_wallet_dashboard', requirements: ['_locale' => '%app.supported_locales%', 'path' => '.*'], defaults: ['path' => ''], methods: ['GET'])]
    public function index(): Response
    {
        $balances = $this->balanceRepository->findBy([], ['displayOrder' => 'ASC']);

        $balancesData = array_map(fn (Balance $balance) => [
            'code' => $balance->getCurrency()->value,
            'amount' => (float) $balance->getAmount(),
            'symbol' => $balance->getCurrency()->getSymbol(),
            'icon' => $balance->getCurrency()->getIcon(),
            'label' => $this->translator->trans($balance->getCurrency()->getTranslationKey(), [], 'multi_currency_wallet'),
            'decimals' => $balance->getCurrency()->getDecimals(),
        ], $balances);

        return $this->render('multi_currency_wallet/index.html.twig', [
            'initial_balances' => $balancesData,
        ]);
    }
}
