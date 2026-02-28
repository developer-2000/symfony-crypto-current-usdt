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
        $result = $this->repository->findForHistory(null, null, 24);

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

    public function testFindForHistoryWithFromTo(): void
    {
        $from = new \DateTimeImmutable('2020-01-01 00:00:00', new \DateTimeZone('UTC'));
        $to = new \DateTimeImmutable('2020-01-02 00:00:00', new \DateTimeZone('UTC'));
        $result = $this->repository->findForHistory($from, $to, null);

        $this->assertIsArray($result);
        foreach ($result as $snapshot) {
            $this->assertGreaterThanOrEqual($from, $snapshot->getCalculatedAt());
            $this->assertLessThanOrEqual($to, $snapshot->getCalculatedAt());
        }
    }
}
