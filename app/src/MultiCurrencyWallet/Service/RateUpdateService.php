<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Service;

use App\MultiCurrencyWallet\Entity\ExchangeRate;
use App\MultiCurrencyWallet\Enum\CurrencyEnum;
use App\MultiCurrencyWallet\Service\RateProvider\Dto\RateDto;
use App\MultiCurrencyWallet\Service\RateProvider\ExchangeRateProviderInterface;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Služba odpovědná za aktualizaci směnných kurzů.
 * Iteruje přes dostupné poskytovatele (RateProviders) a pokouší se stáhnout aktuální kurzy.
 * Implementuje failover logiku: pokud první poskytovatel selže, zkusí dalšího v pořadí.
 */
readonly class RateUpdateService
{
    /**
     * @param iterable<ExchangeRateProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.rate_provider')]
        private iterable $providers,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Spustí proces aktualizace kurzů.
     * Zkouší poskytovatele jednoho po druhém podle priority.
     *
     * @return string název poskytovatele, který úspěšně aktualizoval kurzy
     *
     * @throws \RuntimeException pokud žádný poskytovatel není nakonfigurován nebo všichni selžou
     */
    public function updateRates(): string
    {
        $lastException = null;
        $attempted = false;

        foreach ($this->providers as $provider) {
            $attempted = true;

            try {
                $this->logger->info(\sprintf('Starting rate update via %s', $provider->getName()));

                $rates = $provider->fetchRates();
                $this->persistRates($rates);

                $this->logger->info(\sprintf('Successfully updated rates via %s', $provider->getName()));

                return $provider->getName(); // Zastavíme po prvním úspěchu
            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->warning(\sprintf('Failed to update rates via %s: %s', $provider->getName(), $e->getMessage()));
                continue; // Zkusíme dalšího poskytovatele
            }
        }

        if (!$attempted) {
            throw new \RuntimeException('No rate providers configured.');
        }

        throw new \RuntimeException('All exchange rate providers failed. Last error: ' . ($lastException ? $lastException->getMessage() : 'Unknown error'));
    }

    /**
     * Uloží stažené kurzy do databáze.
     * Filtruje pouze podporované měny definované v CurrencyEnum.
     *
     * @param RateDto[] $rates
     */
    private function persistRates(array $rates): void
    {
        $now = new \DateTimeImmutable();

        foreach ($rates as $dto) {
            $base = CurrencyEnum::tryFrom($dto->sourceCurrency);
            $target = CurrencyEnum::tryFrom($dto->targetCurrency);

            if (!$base || !$target) {
                // Přeskočíme nepodporované měny
                continue;
            }

            $exchangeRate = new ExchangeRate(
                baseCurrency: $base,
                targetCurrency: $target,
                rate: BigDecimal::of($dto->rate),
                fetchedAt: $now
            );

            $this->entityManager->persist($exchangeRate);
        }

        $this->entityManager->flush();
    }
}
