<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model;

use Magento\Framework\App\ResourceConnection;

class OrderImportState
{
    public const STATUS_NEW = 'new';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_RETRYABLE = 'retryable';
    public const STATUS_FAILED = 'failed';
    private const MAX_ATTEMPTS = 10;
    private const TABLE = 'allegro_order_import_state';

    /** @var ResourceConnection */
    private $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function markProcessing(string $checkoutFormId): void
    {
        $connection = $this->connection();
        $connection->insertOnDuplicate($this->table(), [
            'checkout_form_id' => $checkoutFormId,
            'status' => self::STATUS_PROCESSING,
            'attempts' => 0,
            'last_error' => null,
        ], ['status', 'last_error']);
        $connection->update(
            $this->table(),
            ['attempts' => new \Zend_Db_Expr('attempts + 1')],
            ['checkout_form_id = ?' => $checkoutFormId]
        );
    }

    public function markNew(string $checkoutFormId): void
    {
        $this->update($checkoutFormId, ['status' => self::STATUS_NEW, 'last_error' => null]);
    }

    public function markImported(string $checkoutFormId, ?int $orderId): void
    {
        $this->update($checkoutFormId, [
            'status' => self::STATUS_IMPORTED,
            'magento_order_id' => $orderId,
            'last_error' => null,
        ]);
    }

    public function markFailure(string $checkoutFormId, \Throwable $exception): void
    {
        $attempts = (int)$this->connection()->fetchOne(
            $this->connection()->select()->from($this->table(), ['attempts'])
                ->where('checkout_form_id = ?', $checkoutFormId)
        );
        $this->update($checkoutFormId, [
            'status' => $attempts >= self::MAX_ATTEMPTS ? self::STATUS_FAILED : self::STATUS_RETRYABLE,
            'last_error' => $this->safeError($exception),
        ]);
    }

    private function update(string $checkoutFormId, array $data): void
    {
        $this->connection()->update($this->table(), $data, ['checkout_form_id = ?' => $checkoutFormId]);
    }

    private function safeError(\Throwable $exception): string
    {
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $exception->getMessage());
        $message = preg_replace('/\b(?:Bearer\s+)?[A-Za-z0-9+\/_=-]{32,}\b/i', '[secret]', (string)$message);
        return mb_substr(get_class($exception) . ': ' . trim((string)$message), 0, 1000);
    }

    private function connection()
    {
        return $this->resourceConnection->getConnection();
    }

    private function table(): string
    {
        return $this->resourceConnection->getTableName(self::TABLE);
    }
}
