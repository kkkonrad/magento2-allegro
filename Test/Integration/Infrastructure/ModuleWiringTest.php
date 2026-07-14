<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Integration\Infrastructure;

use Macopedia\Allegro\Model\Api\Client;
use Macopedia\Allegro\Model\Configuration;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 */
class ModuleWiringTest extends TestCase
{
    public function testCriticalServicesAndDeclarativeTablesAreAvailable(): void
    {
        $objectManager = ObjectManager::getInstance();

        self::assertInstanceOf(Client::class, $objectManager->get(Client::class));

        $configuration = $objectManager->get(Configuration::class);
        self::assertSame(10, $configuration->getConnectTimeout());
        self::assertSame(120, $configuration->getRequestTimeout());

        $connection = $objectManager->get(ResourceConnection::class)->getConnection();
        foreach ([
            'allegro_offer_mapping_reconciliation',
            'allegro_offer_sync_state',
            'allegro_outbound_sync_state',
            'allegro_async_failures',
            'allegro_order_import_state',
            'allegro_offer_mapping',
        ] as $table) {
            self::assertTrue($connection->isTableExists($table), $table . ' should exist');
        }

        $reservationIndexes = $connection->getIndexList($connection->getTableName('allegro_reservations'));
        $idempotencyIndexes = array_filter(
            $reservationIndexes,
            static function (array $index): bool {
                return strtolower((string)($index['INDEX_TYPE'] ?? '')) === 'unique'
                    && ($index['COLUMNS_LIST'] ?? []) === ['checkout_form_id', 'sku'];
            }
        );
        self::assertCount(1, $idempotencyIndexes);
    }
}
