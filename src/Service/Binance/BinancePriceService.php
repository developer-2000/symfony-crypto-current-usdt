<?php

declare(strict_types=1);

namespace App\Service\Binance;

use App\Exception\BinanceApiException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class BinancePriceService implements BinancePriceServiceInterface
{
    private const AVG_PRICE_PATH = '/api/v3/avgPrice';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUri,
        private readonly int $timeout,
        private readonly LoggerInterface $logger,
        private readonly array $symbols,
    ) {
    }

    /**
     * @param list<string> $symbols
     * @return array<string, float>
     *
     * @throws BinanceApiException
     */
    public function getAvgPrices(array $symbols): array
    {
        $symbols = array_values(array_unique($symbols));
        $result = [];

        foreach ($symbols as $symbol) {
            try {
                $url = rtrim($this->baseUri, '/') . self::AVG_PRICE_PATH;
                $response = $this->httpClient->request('GET', $url, [
                    'query' => ['symbol' => $symbol],
                    'timeout' => $this->timeout,
                ]);
                $statusCode = $response->getStatusCode();
                if ($statusCode !== 200) {
                    $this->logger->warning('Binance API non-200 response', [
                        'symbol' => $symbol,
                        'status' => $statusCode,
                        'body' => $response->getContent(false),
                    ]);
                    throw new BinanceApiException(
                        sprintf('Binance API returned %d for symbol %s', $statusCode, $symbol),
                        $statusCode
                    );
                }
                $data = $response->toArray();
                if (!isset($data['price']) || !is_numeric($data['price'])) {
                    $this->logger->warning('Binance API invalid response format', ['symbol' => $symbol, 'data' => $data]);
                    throw new BinanceApiException(sprintf('Binance API invalid response for symbol %s', $symbol));
                }
                $result[$symbol] = (float) $data['price'];
            } catch (BinanceApiException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->logger->error('Binance API request failed', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                ]);
                throw new BinanceApiException(
                    sprintf('Binance API failed for symbol %s: %s', $symbol, $e->getMessage()),
                    0,
                    $e
                );
            }
        }

        return $result;
    }
}
