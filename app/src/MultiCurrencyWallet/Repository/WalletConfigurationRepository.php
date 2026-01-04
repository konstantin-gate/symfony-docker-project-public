<?php

declare(strict_types=1);

namespace App\MultiCurrencyWallet\Repository;

use App\MultiCurrencyWallet\Entity\WalletConfiguration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WalletConfiguration>
 *
 * @method WalletConfiguration|null find($id, $lockMode = null, $lockVersion = null)
 * @method WalletConfiguration|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method WalletConfiguration[]    findAll()
 * @method WalletConfiguration[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class WalletConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletConfiguration::class);
    }

    /**
     * Získá aktuální konfiguraci. Pokud neexistuje, vytvoří novou s výchozími hodnotami.
     */
    public function getConfiguration(): WalletConfiguration
    {
        $config = $this->findOneBy([]);

        if (!$config) {
            $config = new WalletConfiguration();
            $this->getEntityManager()->persist($config);
            $this->getEntityManager()->flush();
        }

        return $config;
    }
}
