<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\BinanceApiException;
use App\Service\Binance\BinancePriceService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BinancePriceServiceTest extends TestCase
{
    public function testGetAvgPricesReturnsPricesOnSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['mins' => 5, 'price' => '97234.50']);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $logger = $this->createMock(LoggerInterface::class);

        $service = new BinancePriceService($httpClient, 'https://api.binance.com', 10, $logger, ['BTCUSDT']);
        $result = $service->getAvgPrices(['BTCUSDT']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('BTCUSDT', $result);
        $this->assertSame(97234.5, $result['BTCUSDT']);
    }

    public function testGetAvgPricesThrowsOnNon200(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(503);
        $response->method('getContent')->with(false)->willReturn('Service Unavailable');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $logger = $this->createMock(LoggerInterface::class);

        $service = new BinancePriceService($httpClient, 'https://api.binance.com', 10, $logger, ['BTCUSDT']);

        $this->expectException(BinanceApiException::class);
        $this->expectExceptionMessage('Binance API returned 503');
        $service->getAvgPrices(['BTCUSDT']);
    }

    public function testGetAvgPricesThrowsOnInvalidResponseFormat(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['mins' => 5]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $logger = $this->createMock(LoggerInterface::class);

        $service = new BinancePriceService($httpClient, 'https://api.binance.com', 10, $logger, ['BTCUSDT']);

        $this->expectException(BinanceApiException::class);
        $this->expectExceptionMessage('invalid response');
        $service->getAvgPrices(['BTCUSDT']);
    }
}
