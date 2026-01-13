<?php

declare(strict_types=1);

namespace App\PolygraphyDigest\Message;

/**
 * Zpráva pro asynchronní zpracování konkrétního zdroje dat.
 * Tato zpráva je odesílána do fronty, když je potřeba spustit proces stahování pro daný zdroj.
 */
final readonly class ProcessSourceMessage
{
    /**
     * Vytvoří novou instanci zprávy.
     *
     * @param string $sourceId UUID identifikátor zdroje, který má být zpracován.
     */
    public function __construct(
        public string $sourceId,
    ) {
    }
}
