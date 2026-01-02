<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MultiCurrencyWalletController extends AbstractController
{
    #[Route('/multi-currency-wallet/{path}', name: 'multi_currency_wallet_dashboard_default', requirements: ['path' => '.*'], defaults: ['_locale' => 'cs', 'path' => ''], methods: ['GET'])]
    #[Route('/{_locale}/multi-currency-wallet/{path}', name: 'multi_currency_wallet_dashboard', requirements: ['_locale' => '%app.supported_locales%', 'path' => '.*'], defaults: ['path' => ''], methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('multi_currency_wallet/index.html.twig', []);
    }
}
