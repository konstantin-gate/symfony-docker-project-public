<?php

declare(strict_types=1);

namespace App\MoneyCalculator\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MoneyCalculatorController extends AbstractController
{
    #[Route('/money-calculator', name: 'money_calculator_dashboard_default', defaults: ['_locale' => 'cs'], methods: ['GET'])]
    #[Route('/{_locale}/money-calculator', name: 'money_calculator_dashboard', requirements: ['_locale' => '%app.supported_locales%'], methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('money_calculator/index.html.twig', []);
    }
}
