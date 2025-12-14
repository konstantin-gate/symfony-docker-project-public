<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    #[Route('/', name: 'app_root')]
    public function root(): Response
    {
        return $this->redirectToRoute('index_default', ['_locale' => 'cs']);
    }

    #[Route('/{_locale}', name: 'index_default', requirements: ['_locale' => 'en|cs|ru'])]
    public function index(): Response
    {
        return $this->render('@Index/index.html.twig', [
            'controller_name' => 'IndexController',
        ]);
    }
}
