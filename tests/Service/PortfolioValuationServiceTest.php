<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\PortfolioSnapshot;
use App\Service\Binance\BinancePriceServiceInterface;
use App\Service\PortfolioValuationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PortfolioValuationServiceTest extends TestCase
{
    public function testSnapshotCalculatesCorrectAmountAndPersists(): void
    {
        $prices = [
            'BTCUSDT' => 100000.0,
            'ETHUSDT' => 3000.0,
            'SOLUSDT' => 200.0,
        ];
        $binance = $this->createMock(BinancePriceServiceInterface::class);
        $binance->method('getAvgPrices')->willReturn($prices);

        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted = $entity;
        });
        $em->expects(self::once())->method('flush');

        $amounts = ['btc' => 1, 'eth' => 10, 'sol' => 50, 'usdt' => 5000.0];
        $symbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'];

        $service = new PortfolioValuationService($binance, $em, $amounts, $symbols);
        $service->snapshot();

        $this->assertInstanceOf(PortfolioSnapshot::class, $persisted);
        $expectedUsdt = 5000.0 + 1 * 100000.0 + 10 * 3000.0 + 50 * 200.0; // 5000 + 100000 + 30000 + 10000 = 145000
        $this->assertSame(number_format($expectedUsdt, 8, '.', ''), $persisted->getAmountUsdt());
    }

    public function testSnapshotSwallowsUniqueConstraintViolation(): void
    {
        $binance = $this->createMock(BinancePriceServiceInterface::class);
        $binance->method('getAvgPrices')->willReturn(['BTCUSDT' => 1.0, 'ETHUSDT' => 1.0, 'SOLUSDT' => 1.0]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $e = $this->getMockBuilder(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class)
            ->disableOriginalConstructor()
            ->getMock();
        $em->expects(self::once())->method('flush')->willThrowException($e);
        $em->expects(self::once())->method('clear');

        $amounts = ['btc' => 1, 'eth' => 0, 'sol' => 0, 'usdt' => 0.0];
        $service = new PortfolioValuationService($binance, $em, $amounts, ['BTCUSDT', 'ETHUSDT', 'SOLUSDT']);
        $service->snapshot();
    }
}
