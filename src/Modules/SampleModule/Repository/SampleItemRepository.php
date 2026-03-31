<?php

declare(strict_types=1);

namespace App\Modules\SampleModule\Repository;

use App\Modules\SampleModule\Entity\SampleItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SampleItem>
 */
final class SampleItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SampleItem::class);
    }
}
