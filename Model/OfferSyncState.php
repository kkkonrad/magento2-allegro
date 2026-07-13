<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Model\Api\Credentials;
use Magento\Framework\App\ResourceConnection;

class OfferSyncState
{
    private const TABLE = 'allegro_offer_sync_state';

    /** @var ResourceConnection */
    private $resourceConnection;

    /** @var Credentials */
    private $credentials;

    public function __construct(ResourceConnection $resourceConnection, Credentials $credentials)
    {
        $this->resourceConnection = $resourceConnection;
        $this->credentials = $credentials;
    }

    public function createHash(string $offerId, int $quantity, ?float $price): string
    {
        return hash('sha256', json_encode([
            'offer_id' => $offerId,
            'quantity' => $quantity,
            'price' => $price !== null ? number_format($price, 2, '.', '') : null,
        ], JSON_THROW_ON_ERROR));
    }

    public function isCurrent(int $productId, string $stateHash): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->tableName(), ['state_hash'])
            ->where('product_id = ?', $productId)
            ->where('environment = ?', $this->environment())
            ->limit(1);

        return hash_equals((string)$connection->fetchOne($select), $stateHash);
    }

    public function markCurrent(int $productId, string $offerId, string $stateHash): void
    {
        $this->resourceConnection->getConnection()->insertOnDuplicate(
            $this->tableName(),
            [
                'product_id' => $productId,
                'offer_id' => $offerId,
                'environment' => $this->environment(),
                'state_hash' => $stateHash,
            ],
            ['offer_id', 'state_hash']
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
