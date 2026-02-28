<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\PortfolioSnapshotRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PortfolioSnapshotRepositoryTest extends KernelTestCase
{
    private ?PortfolioSnapshotRepository $repository = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(PortfolioSnapshotRepository::class);
    }

    public function testFindForHistoryReturnsArrayOrderedByCalculatedAt(): void
    {
        $result = $this->repository->findForHistory(24, null);

        $this->assertIsArray($result);
        $prev = null;
        foreach ($result as $snapshot) {
            $this->assertInstanceOf(\App\Entity\PortfolioSnapshot::class, $snapshot);
            $at = $snapshot->getCalculatedAt();
            if ($prev !== null) {
                $this->assertGreaterThanOrEqual($prev, $at, 'Results must be ordered by calculatedAt ASC');
            }
            $prev = $at;
        }
    }

    public function testFindForHistoryWithMinutes(): void
    {
        $result = $this->repository->findForHistory(null, 60);

        $this->assertIsArray($result);
    }
}
