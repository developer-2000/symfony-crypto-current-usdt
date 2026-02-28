<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PortfolioHistoryControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        static::ensureKernelShutdown();
        parent::tearDown();
    }

    public function testHistoryReturns200AndJsonArray(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/portfolio/history', ['hours' => '24']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testHistoryEachItemHasTimeAndAmountUsdt(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/portfolio/history', ['hours' => '24']);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        foreach ($data as $item) {
            $this->assertArrayHasKey('time', $item);
            $this->assertArrayHasKey('amount_usdt', $item);
            $this->assertIsNumeric($item['amount_usdt']);
        }
    }

    public function testHistoryOrderAscByTime(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/portfolio/history', ['hours' => '24']);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $prev = null;
        foreach ($data as $item) {
            $t = $item['time'] ?? '';
            if ($prev !== null) {
                $this->assertGreaterThanOrEqual($prev, $t, 'Items must be ordered by time ASC');
            }
            $prev = $t;
        }
    }

    public function testHistoryInvalidHoursReturns422(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/portfolio/history', ['hours' => 'invalid']);

        $this->assertResponseStatusCodeSame(422);
        $content = $client->getResponse()->getContent();
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
    }

    public function testHistoryHoursOutOfRangeReturns422(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/portfolio/history', ['hours' => '99999']);

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }
}
