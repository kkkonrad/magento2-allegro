<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Api\ProductRepositoryInterface;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Api\Credentials;
use Magento\Framework\App\ResourceConnection;

class OfferMappingService
{
    private const TABLE = 'allegro_offer_mapping_reconciliation';
    private const MAX_ATTEMPTS = 5;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var ResourceConnection */
    private $resourceConnection;

    /** @var Credentials */
    private $credentials;

    /** @var Logger */
    private $logger;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ResourceConnection $resourceConnection,
        Credentials $credentials,
        Logger $logger
    ) {
        $this->productRepository = $productRepository;
        $this->resourceConnection = $resourceConnection;
        $this->credentials = $credentials;
        $this->logger = $logger;
    }

    public function saveMapping(
        int $productId,
        string $offerId,
        ?string $allegroProductId = null
    ): bool {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $product = $this->productRepository->getById($productId, true, null, true);
            $product->setData('allegro_offer_id', $offerId);
            if ($allegroProductId !== null && $allegroProductId !== '') {
                $product->setData('allegro_product_id', $allegroProductId);
            }
            $this->productRepository->save($product);
            $this->markResolved($offerId);
            $connection->commit();
            return true;
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->recordFailure($productId, $offerId, $allegroProductId, $e);
            $this->logger->apiFailure('Could not persist Allegro offer mapping', [
                'product_id' => $productId,
                'offer_id' => $offerId,
                'environment' => $this->environment(),
                'exception_type' => get_class($e),
            ]);
            return false;
        }
    }

    /**
     * @return array{processed:int,resolved:int,failed:int}
     */
    public function reconcilePending(int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));
        $connection = $this->resourceConnection->getConnection();
        $table = $this->tableName();
        $select = $connection->select()
            ->from($table)
            ->where('environment = ?', $this->environment())
            ->where('status IN (?)', ['pending', 'failed'])
            ->where('attempts < ?', self::MAX_ATTEMPTS)
            ->order('entity_id ASC')
            ->limit($limit);

        $result = ['processed' => 0, 'resolved' => 0, 'failed' => 0];
        foreach ($connection->fetchAll($select) as $row) {
            $result['processed']++;
            if ($this->saveMapping(
                (int)$row['product_id'],
                (string)$row['offer_id'],
                $row['allegro_product_id'] !== null ? (string)$row['allegro_product_id'] : null
            )) {
                $result['resolved']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    private function recordFailure(
        int $productId,
        string $offerId,
        ?string $allegroProductId,
        \Exception $exception
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->tableName();
        $where = [
            'offer_id = ?' => $offerId,
            'environment = ?' => $this->environment(),
        ];
        $existing = $connection->fetchRow(
            $connection->select()->from($table)->where('offer_id = ?', $offerId)
                ->where('environment = ?', $this->environment())->limit(1)
        );

        $data = [
            'product_id' => $productId,
            'allegro_product_id' => $allegroProductId,
            'seller_id' => $this->credentials->getClientId(),
            'status' => 'failed',
            'attempts' => min(((int)($existing['attempts'] ?? 0)) + 1, self::MAX_ATTEMPTS),
            'last_error' => mb_substr($exception->getMessage(), 0, 1000),
        ];

        if ($existing) {
            $connection->update($table, $data, $where);
            return;
        }

        $connection->insert($table, array_merge($data, [
            'offer_id' => $offerId,
            'environment' => $this->environment(),
        ]));
    }

    private function markResolved(string $offerId): void
    {
        $this->resourceConnection->getConnection()->update(
            $this->tableName(),
            ['status' => 'resolved', 'last_error' => null],
            [
                'offer_id = ?' => $offerId,
                'environment = ?' => $this->environment(),
            ]
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
