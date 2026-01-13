<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Controller\Api;

use App\PolygraphyDigest\Service\Search\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * API kontroler pro modul PolygraphyDigest.
 * Poskytuje endpointy pro vyhledávání článků, produktů a další související operace.
 */
#[Route('/api/polygraphy', name: 'api_polygraphy_')]
class PolygraphyApiController extends AbstractController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly SerializerInterface $serializer
    ) {
    }
}
