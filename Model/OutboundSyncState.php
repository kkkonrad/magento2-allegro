<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Model\Api\Credentials;
use Magento\Framework\App\ResourceConnection;

class OutboundSyncState
{
    private const TABLE = 'allegro_outbound_sync_state';

    /** @var ResourceConnection */
    private $resourceConnection;

    /** @var Credentials */
    private $credentials;

    public function __construct(ResourceConnection $resourceConnection, Credentials $credentials)
    {
        $this->resourceConnection = $resourceConnection;
        $this->credentials = $credentials;
    }

    public function hash(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    public function isCurrent(string $operation, string $sourceId, string $stateHash): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $current = $connection->fetchOne(
            $connection->select()->from($this->tableName(), ['state_hash'])
                ->where('operation = ?', $operation)
                ->where('source_id = ?', $sourceId)
                ->where('environment = ?', $this->environment())
                ->limit(1)
        );

        return is_string($current) && hash_equals($current, $stateHash);
    }

    public function markCurrent(string $operation, string $sourceId, string $stateHash): void
    {
        $this->resourceConnection->getConnection()->insertOnDuplicate(
            $this->tableName(),
            [
                'operation' => $operation,
                'source_id' => $sourceId,
                'environment' => $this->environment(),
                'state_hash' => $stateHash,
            ],
            ['state_hash']
        );
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
