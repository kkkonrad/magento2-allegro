<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Service;

use Macopedia\Allegro\Api\Consumer\EntityMessageInterfaceFactory;
use Macopedia\Allegro\Logger\Logger;
use Magento\Framework\MessageQueue\DefaultValueProvider;
use Magento\Framework\MessageQueue\PublisherInterface;

class OutboundMessagePublisher
{
    private const STATUS_TOPIC = 'allegro.order.status';
    private const STATUS_DB_TOPIC = 'allegro.order.status.db';
    private const SHIPMENT_TOPIC = 'allegro.shipment';
    private const SHIPMENT_DB_TOPIC = 'allegro.shipment.db';

    /** @var PublisherInterface */
    private $publisher;

    /** @var EntityMessageInterfaceFactory */
    private $messageFactory;

    /** @var DefaultValueProvider */
    private $defaultValueProvider;

    /** @var Logger */
    private $logger;

    public function __construct(
        PublisherInterface $publisher,
        EntityMessageInterfaceFactory $messageFactory,
        DefaultValueProvider $defaultValueProvider,
        Logger $logger
    ) {
        $this->publisher = $publisher;
        $this->messageFactory = $messageFactory;
        $this->defaultValueProvider = $defaultValueProvider;
        $this->logger = $logger;
    }

    public function publishOrderStatus(int $orderId): void
    {
        $this->publish($orderId, self::STATUS_TOPIC, self::STATUS_DB_TOPIC);
    }

    public function publishShipment(int $shipmentId): void
    {
        $this->publish($shipmentId, self::SHIPMENT_TOPIC, self::SHIPMENT_DB_TOPIC);
    }

    private function publish(int $entityId, string $topic, string $dbTopic): void
    {
        if ($entityId < 1) {
            return;
        }

        try {
            $message = $this->messageFactory->create();
            $message->setEntityId($entityId);
            $this->publisher->publish(
                $this->defaultValueProvider->getConnection() === 'db' ? $dbTopic : $topic,
                $message
            );
        } catch (\Throwable $exception) {
            $this->logger->apiFailure('Could not publish Allegro outbound message', [
                'entity_id' => $entityId,
                'exception_type' => get_class($exception),
            ]);
        }
    }
}
