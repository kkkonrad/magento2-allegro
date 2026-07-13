<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Consumer;

use Macopedia\Allegro\Api\Consumer\EntityMessageInterface;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\AsyncFailureRepository;
use Macopedia\Allegro\Model\OrderImporter\OriginOfOrder;
use Macopedia\Allegro\Model\OutboundSyncState;
use Macopedia\Allegro\Service\ShipmentSender;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;

class ShipmentConsumer
{
    private const OPERATION = 'shipment';

    /** @var ShipmentRepositoryInterface */
    private $shipmentRepository;
    /** @var Configuration */
    private $configuration;
    /** @var OriginOfOrder */
    private $orderOrigin;
    /** @var ShipmentSender */
    private $shipmentSender;
    /** @var OutboundSyncState */
    private $syncState;
    /** @var LockManagerInterface */
    private $lockManager;
    /** @var Logger */
    private $logger;
    /** @var AsyncFailureRepository */
    private $asyncFailureRepository;

    public function __construct(
        ShipmentRepositoryInterface $shipmentRepository,
        Configuration $configuration,
        OriginOfOrder $orderOrigin,
        ShipmentSender $shipmentSender,
        OutboundSyncState $syncState,
        LockManagerInterface $lockManager,
        Logger $logger,
        AsyncFailureRepository $asyncFailureRepository
    ) {
        $this->shipmentRepository = $shipmentRepository;
        $this->configuration = $configuration;
        $this->orderOrigin = $orderOrigin;
        $this->shipmentSender = $shipmentSender;
        $this->syncState = $syncState;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
        $this->asyncFailureRepository = $asyncFailureRepository;
    }

    public function process(EntityMessageInterface $message): void
    {
        $shipmentId = (int)$message->getEntityId();
        if ($shipmentId < 1 || !$this->configuration->isTrackingNumberSendingEnabled()) {
            return;
        }
        $lockName = 'macopedia_allegro_shipment_' . $shipmentId;
        if (!$this->lockManager->lock($lockName, 0)) {
            throw new \RuntimeException('Shipment synchronization is already running.');
        }

        try {
            $shipment = $this->shipmentRepository->get($shipmentId);
            if (!$this->orderOrigin->isOrderFromAllegro($shipment->getOrder())) {
                return;
            }
            $hash = $this->syncState->hash($this->shipmentSender->state($shipment));
            if ($this->syncState->isCurrent(self::OPERATION, (string)$shipmentId, $hash)) {
                return;
            }
            $this->shipmentSender->send($shipment);
            $this->syncState->markCurrent(self::OPERATION, (string)$shipmentId, $hash);
        } catch (\Throwable $exception) {
            $this->asyncFailureRepository->recordFailure(
                AsyncFailureRepository::OPERATION_SHIPMENT,
                $shipmentId,
                $exception
            );
            $this->logger->apiFailure('Allegro shipment synchronization failed', [
                'shipment_id' => $shipmentId,
                'exception_type' => get_class($exception),
            ]);
            throw $exception;
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }
}
