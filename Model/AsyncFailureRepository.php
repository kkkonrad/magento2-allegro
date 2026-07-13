<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Model\Api\Credentials;
use Magento\Framework\App\ResourceConnection;

class AsyncFailureRepository
{
    public const OPERATION_STOCK = 'stock';
    public const OPERATION_ORDER_STATUS = 'order_status';
    public const OPERATION_SHIPMENT = 'shipment';
    public const MAX_ATTEMPTS = 5;
    private const TABLE = 'allegro_async_failures';

    /** @var ResourceConnection */
    private $resourceConnection;
    /** @var Credentials */
    private $credentials;

    public function __construct(ResourceConnection $resourceConnection, Credentials $credentials)
    {
        $this->resourceConnection = $resourceConnection;
        $this->credentials = $credentials;
    }

    public function recordFailure(string $operation, int $sourceId, \Throwable $exception): void
    {
        $connection = $this->resourceConnection->getConnection();
        $existing = $connection->fetchRow(
            $connection->select()->from($this->tableName())
                ->where('operation = ?', $operation)
                ->where('source_id = ?', $sourceId)
                ->where('environment = ?', $this->environment())
                ->limit(1)
        );
        $attempts = min(((int)($existing['attempts'] ?? 0)) + 1, self::MAX_ATTEMPTS);
        $delayMinutes = min(60, 2 ** max(0, $attempts - 1));
        $data = [
            'status' => $attempts >= self::MAX_ATTEMPTS ? 'dead' : 'pending',
            'attempts' => $attempts,
            'last_error' => $this->safeError($exception),
            'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + ($delayMinutes * 60)),
        ];

        if ($existing) {
            $connection->update($this->tableName(), $data, ['entity_id = ?' => (int)$existing['entity_id']]);
            return;
        }
        $connection->insert($this->tableName(), array_merge($data, [
            'operation' => $operation,
            'source_id' => $sourceId,
            'environment' => $this->environment(),
        ]));
    }

    public function getDue(int $limit = 100): array
    {
        return $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()->from($this->tableName())
                ->where('environment = ?', $this->environment())
                ->where('status = ?', 'pending')
                ->where('attempts < ?', self::MAX_ATTEMPTS)
                ->where('next_attempt_at <= ?', gmdate('Y-m-d H:i:s'))
                ->order('next_attempt_at ASC')
                ->limit(max(1, min($limit, 1000)))
        );
    }

    public function markResolved(string $operation, int $sourceId): void
    {
        $this->resourceConnection->getConnection()->update(
            $this->tableName(),
            ['status' => 'resolved', 'last_error' => null, 'next_attempt_at' => null],
            [
                'operation = ?' => $operation,
                'source_id = ?' => $sourceId,
                'environment = ?' => $this->environment(),
            ]
        );
    }

    public function getDeadCount(): int
    {
        return (int)$this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()->select()->from($this->tableName(), ['COUNT(*)'])
                ->where('environment = ?', $this->environment())
                ->where('status = ?', 'dead')
        );
    }

    public function getDead(int $limit = 100): array
    {
        return $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()->from(
                $this->tableName(),
                ['operation', 'source_id', 'attempts', 'last_error', 'updated_at']
            )
                ->where('environment = ?', $this->environment())
                ->where('status = ?', 'dead')
                ->order('updated_at DESC')
                ->limit(max(1, min($limit, 1000)))
        );
    }

    private function safeError(\Throwable $exception): string
    {
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $exception->getMessage());
        $message = preg_replace('/\b(?:Bearer\s+)?[A-Za-z0-9+\/_=-]{32,}\b/i', '[secret]', (string)$message);
        return mb_substr(get_class($exception) . ': ' . trim((string)$message), 0, 1000);
    }

    private function environment(): string
    {
        return $this->credentials->isSandbox() ? 'sandbox' : 'production';
    }

    private function tableName(): string
    {
        return $this->resourceConnection->getTableName(self::TABLE);
    }
}
