<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LogManager
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Возвращает динамический логгер.
     * Универсальный: имя можно не передавать — подставится 'app' (файл app.log, канал app).
     *
     * @param string $name используется и как канал Monolog, и как имя файла при type=file: для name='app' пишет только в errors.log (Error); для иного имени — var/log/{name}.log.
     * @param string $type 'file'|'db'
     */
    public function getLogger(string $name = 'app', string $type = 'file'): LoggerInterface
    {
        $logger = new Logger($name);

        if ($type === 'file') {
            $logDir = $this->projectDir . '/var/log';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            if ($name === 'app') {
                $logger->pushHandler(new StreamHandler($logDir . '/errors.log', Level::Error));
            } else {
                $file = $logDir . '/' . $name . '.log';
                $logger->pushHandler(new StreamHandler($file, Level::Info));
            }
        } elseif ($type === 'db') {
            $logger->pushHandler(new class ($this->connection) extends AbstractProcessingHandler {
                public function __construct(private readonly Connection $connection)
                {
                    parent::__construct(Level::Info);
                }

                protected function write(\Monolog\LogRecord $record): void
                {
                    $this->connection->insert('app_log', [
                        'channel' => $record->channel,
                        'level' => $record->level->getName(),
                        'message' => $record->message,
                        'context' => json_encode($record->context),
                        'created_at' => $record->datetime->format('Y-m-d H:i:s'),
                    ]);
                }
            });
        }

        return $logger;
    }
}
