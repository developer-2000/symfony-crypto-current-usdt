<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\BinanceApiException;
use App\Repository\PortfolioSnapshotRepository;
use App\Service\LogManager;
use App\Service\PortfolioValuationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:portfolio:snapshot',
    description: 'Creates hourly portfolio valuation snapshot in USDT (run by cron).',
)]
final class PortfolioSnapshotCommand extends Command
{
    private const MERCURE_TOPIC = 'portfolio/snapshots';

    public function __construct(
        private readonly PortfolioValuationService $portfolioValuationService,
        private readonly PortfolioSnapshotRepository $portfolioSnapshotRepository,
        private readonly LoggerInterface $logger,
        private readonly LogManager $logManager,
        private readonly HubInterface $hub,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $out = fn (string $msg) => $output->writeln(date('[Y-m-d\TH:i:sP] ') . $msg);

        $out('snapshot command started (granularity=' . $this->portfolioValuationService->getSnapshotGranularity() . ')');

        try {
            $out('calling Binance + DB snapshot()');
            $data = $this->portfolioValuationService->snapshot();

            $context = $data !== null
                ? ['calculated_at' => $data['calculated_at'], 'amount_usdt' => $data['amount_usdt']]
                : ['duplicate_hour' => true];
            $this->logger->info('Portfolio snapshot created successfully.', $context);
            $this->logManager->getLogger('events', 'db')->info('Portfolio snapshot created successfully.', $context);
            $payload = $data;
            if ($payload === null) {
                $last = $this->portfolioSnapshotRepository->findLatest();
                if ($last !== null) {
                    $payload = [
                        'calculated_at' => $last->getCalculatedAt()->format('Y-m-d\TH:i:sP'),
                        'amount_usdt' => (float) $last->getAmountUsdt(),
                    ];
                    $out('publishing last snapshot to Mercure (duplicate hour)');
                }
            }
            if ($payload !== null) {
                $out('publishing to Mercure topic=' . self::MERCURE_TOPIC);
                $this->hub->publish(new Update(self::MERCURE_TOPIC, json_encode($payload)));
                $out('Mercure publish done');
            } else {
                $out('skip Mercure (no snapshot in DB)');
            }
            $io->success('Portfolio snapshot saved.');

            return Command::SUCCESS;
        } catch (BinanceApiException $e) {
            $out('ERROR BinanceApi: ' . $e->getMessage());
            $this->logger->error('Portfolio snapshot failed: Binance API error.', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            $this->logManager->getLogger('events', 'db')->error('Portfolio snapshot failed: Binance API error.', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            $io->error('Binance API error: ' . $e->getMessage());

            return Command::FAILURE;
        } catch (\Throwable $e) {
            $out('ERROR ' . $e::class . ': ' . $e->getMessage());
            $this->logger->error('Portfolio snapshot failed.', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            $this->logManager->getLogger('events', 'db')->error('Portfolio snapshot failed.', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            $io->error('Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
