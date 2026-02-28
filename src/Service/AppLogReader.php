<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Возвращает последние записи из app_log по каналу events для отображения в блоке событий на фронте.
 */
final class AppLogReader
{
    private const CHANNEL_EVENTS = 'events';

    public function __construct(
        private readonly Connection $connection,
        private readonly int $defaultLimit,
    ) {
    }

    /**
     * Выбирает из app_log записи канала events, сортировка по дате создания по убыванию.
     * Используется API GET /api/events для блока «Журнал событий».
     *
     * @return list<array{id: int, channel: string, level: string, message: string, context: string|null, created_at: string}>
     */
    public function getRecentEvents(?int $limit = null): array
    {
        $limit = $limit ?? $this->defaultLimit;
        $sql = 'SELECT id, channel, level, message, context, created_at FROM app_log WHERE channel = ? ORDER BY created_at DESC LIMIT ' . (int) $limit;
        $result = $this->connection->executeQuery($sql, [self::CHANNEL_EVENTS], ['string']);
        $rows = $result->fetchAllAssociative();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'channel' => (string) $row['channel'],
                'level' => (string) $row['level'],
                'message' => (string) $row['message'],
                'context' => isset($row['context']) ? (string) $row['context'] : null,
                'created_at' => (string) $row['created_at'],
            ];
        }
        return $out;
    }
}
