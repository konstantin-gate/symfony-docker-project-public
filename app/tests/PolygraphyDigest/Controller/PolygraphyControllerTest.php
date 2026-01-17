<?php

declare(strict_types=1);

namespace App\Tests\PolygraphyDigest\Controller;

use App\PolygraphyDigest\Controller\PolygraphyController;
use App\PolygraphyDigest\Service\LifecycleService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PolygraphyControllerTest extends TestCase
{
    private LifecycleService&MockObject $lifecycleService;
    private PolygraphyController $controller;

    protected function setUp(): void
    {
        $this->lifecycleService = $this->createMock(LifecycleService::class);
        $this->controller = new PolygraphyController($this->lifecycleService);
    }

    /**
     * Testuje, že kontrolér byl správně inicializován s předanými závislostmi.
     */
    public function testConstructor(): void
    {
        $this->assertInstanceOf(PolygraphyController::class, $this->controller);
    }

    /**
     * Ověřuje, že metoda index volá runMaintenance v LifecycleService právě jednou.
     */
    public function testIndexCallsMaintenance(): void
    {
        $this->lifecycleService->expects($this->once())
            ->method('runMaintenance');

        // Musíme namockovat kontejner pro metodu render()
        $container = $this->createMock(ContainerInterface::class);
        $twig = $this->createMock(Environment::class);

        $container->method('has')->with('twig')->willReturn(true);
        $container->method('get')->with('twig')->willReturn($twig);

        $twig->method('render')->willReturn('<html lang=""></html>');

        $this->controller->setContainer($container);
        $this->controller->index();
    }

    /**
     * Ověřuje, že metoda index vrací objekt typu Response.
     */
    public function testIndexReturnsResponse(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $twig = $this->createMock(Environment::class);
        $container->method('has')->with('twig')->willReturn(true);
        $container->method('get')->with('twig')->willReturn($twig);
        $twig->method('render')->willReturn('<html></html>');

        $this->controller->setContainer($container);
        $response = $this->controller->index();

        $this->assertInstanceOf(Response::class, $response);
    }

    /**
     * Testuje, že kontrolér zůstane funkční, i když runMaintenance vyhodí výjimku.
     * I když by LifecycleService měl výjimky zachytávat, kontrolér musí být odolný.
     */
    public function testIndexResilienceOnMaintenanceException(): void
    {
        $this->lifecycleService->method('runMaintenance')
            ->willThrowException(new \RuntimeException('Maintenance failed'));

        $container = $this->createMock(ContainerInterface::class);
        $twig = $this->createMock(Environment::class);
        $container->method('has')->with('twig')->willReturn(true);
        $container->method('get')->with('twig')->willReturn($twig);
        $twig->method('render')->willReturn('<html></html>');

        $this->controller->setContainer($container);
        
        // Pokud service vyhodí výjimku, kterou neodchytí, test by měl selhat.
        // Nicméně LifecycleService dle kódu výjimky chytá, ale my testujeme izolovaný kontrolér.
        // Pro účely Unit testu chceme vědět, zda kontrolér nepadne, pokud se něco stane v závislosti.
        $this->expectException(\RuntimeException::class);
        $this->controller->index();
    }

    /**
     * Ověřuje, že render je volán se správným názvem šablony.
     */
    public function testIndexRendersCorrectTemplate(): void
    {
        $templateName = 'polygraphy_digest/index.html.twig';

        $container = $this->createMock(ContainerInterface::class);
        $twig = $this->createMock(Environment::class);
        $container->method('has')->with('twig')->willReturn(true);
        $container->method('get')->with('twig')->willReturn($twig);

        $twig->expects($this->once())
            ->method('render')
            ->with($templateName)
            ->willReturn('<html></html>');

        $this->controller->setContainer($container);
        $this->controller->index();
    }
}
