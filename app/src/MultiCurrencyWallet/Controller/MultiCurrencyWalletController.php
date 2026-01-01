<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MultiCurrencyWalletController extends AbstractController
{
    #[Route('/multi-currency-wallet', name: 'multi_currency_wallet_dashboard_default', defaults: ['_locale' => 'cs'], methods: ['GET'])]
    #[Route('/{_locale}/multi-currency-wallet', name: 'multi_currency_wallet_dashboard', requirements: ['_locale' => '%app.supported_locales%'], methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('multi_currency_wallet/index.html.twig', []);
    }
}
