<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller;

use App\MultiCurrencyWallet\Entity\Balance;
use App\MultiCurrencyWallet\Repository\BalanceRepository;
use App\MultiCurrencyWallet\Repository\ExchangeRateRepository;
use App\MultiCurrencyWallet\Repository\WalletConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Hlavní kontroler pro modul Multi-Currency Wallet.
 * Zajišťuje vykreslení SPA aplikace (React) a předání počátečních dat (zůstatky, konfigurace).
 * Obsahuje logiku pro určení, zda je potřeba automatická aktualizace kurzů.
 */
class MultiCurrencyWalletController extends AbstractController
{
    public function __construct(
        private readonly BalanceRepository $balanceRepository,
        private readonly ExchangeRateRepository $exchangeRateRepository,
        private readonly WalletConfigurationRepository $configurationRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Vykreslí hlavní stránku peněženky.
     * Načte aktuální zůstatky a rozhodne, zda se má na klientovi spustit automatická aktualizace kurzů
     * (pokud je po 9:00 ráno a kurzy ještě nebyly dnes aktualizovány).
     */
    #[Route('/multi-currency-wallet/{path}', name: 'multi_currency_wallet_dashboard_default', requirements: ['path' => '.*'], defaults: ['_locale' => 'cs', 'path' => ''], methods: ['GET'])]
    #[Route('/{_locale}/multi-currency-wallet/{path}', name: 'multi_currency_wallet_dashboard', requirements: ['_locale' => '%app.supported_locales%', 'path' => '.*'], defaults: ['path' => ''], methods: ['GET'])]
    public function index(): Response
    {
        $balances = $this->balanceRepository->findBy([], ['displayOrder' => 'ASC']);
        $config = $this->configurationRepository->getConfiguration();

        $balancesData = array_map(fn (Balance $balance) => [
            'code' => $balance->getCurrency()->value,
            'amount' => (float) $balance->getAmount(),
            'symbol' => $balance->getCurrency()->getSymbol(),
            'icon' => $balance->getCurrency()->getIcon(),
            'label' => $this->translator->trans($balance->getCurrency()->getTranslationKey(), [], 'multi_currency_wallet'),
            'decimals' => $balance->getCurrency()->getDecimals(),
        ], $balances);

        // Logika automatické aktualizace:
        // Zkontrolujeme, zda je auto-update povolen v nastavení,
        // zda je po 9:00 ráno (Praha) a kurzy od té doby nebyly aktualizovány.
        $autoUpdateNeeded = false;

        if ($config->isAutoUpdateEnabled()) {
            try {
                $pragueTz = new \DateTimeZone('Europe/Prague');
                $now = new \DateTimeImmutable('now', $pragueTz);
                $today9am = $now->setTime(9, 0, 0);

                if ($now >= $today9am) {
                    $latestRate = $this->exchangeRateRepository->findLatestUpdate();

                    if (!$latestRate || $latestRate->getFetchedAt() < $today9am) {
                        $autoUpdateNeeded = true;
                    }
                }
            } catch (\Exception $e) {
                // V případě chyby tiše ignorujeme kontrolu automatické aktualizace.
            }
        }

        return $this->render('multi_currency_wallet/index.html.twig', [
            'initial_balances' => $balancesData,
            'auto_update_needed' => $autoUpdateNeeded,
            'wallet_config' => [
                'mainCurrency' => $config->getMainCurrency()->value,
                'autoUpdateEnabled' => $config->isAutoUpdateEnabled(),
            ],
        ]);
    }
}