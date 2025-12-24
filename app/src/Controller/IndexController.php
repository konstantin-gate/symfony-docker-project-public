<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Kontroler pro obsluhu úvodní stránky a přesměrování na výchozí lokalizaci.
 */
final class IndexController extends AbstractController
{
    /**
     * Přesměruje uživatele z kořenové URL na výchozí lokalizaci (čeština).
     */
    #[Route('/', name: 'app_root')]
    public function root(): Response
    {
        return $this->redirectToRoute('index_default', ['_locale' => 'cs']);
    }

    /**
     * Zobrazí úvodní stránku aplikace v závislosti na zvoleném jazyce.
     */
    #[Route('/{_locale}', name: 'index_default', requirements: ['_locale' => 'en|cs|ru'])]
    public function index(): Response
    {
        return $this->render('@Index/index.html.twig', [
            'controller_name' => 'IndexController',
        ]);
    }
}
