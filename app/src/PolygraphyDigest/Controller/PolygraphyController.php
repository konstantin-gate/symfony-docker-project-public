<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Hlavní kontroler pro frontend modulu PolygraphyDigest (React entry point).
 */
class PolygraphyController extends AbstractController
{
    /**
     * Hlavní vstupní bod pro React aplikaci.
     * Zachytává všechny cesty začínající /polygraphy, které nejsou /api.
     */
    #[Route('/polygraphy/{reactRouting}', name: 'polygraphy_index', requirements: ['reactRouting' => '^(?!api).+'], defaults: ['reactRouting' => null])]
    public function index(): Response
    {
        return $this->render('polygraphy_digest/index.html.twig');
    }
}
