<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Consumer;

use Macopedia\Allegro\Api\Consumer\EntityMessageInterface;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\AllegroOrderStatus;
use Macopedia\Allegro\Model\AsyncFailureRepository;
use Macopedia\Allegro\Model\OrderImporter\OriginOfOrder;
use Macopedia\Allegro\Model\OutboundSyncState;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class OrderStatusConsumer
{
    private const OPERATION = 'order_status';

    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /** @var OriginOfOrder */
    private $orderOrigin;
    /** @var AllegroOrderStatus */
    private $allegroOrderStatus;
    /** @var OutboundSyncState */
    private $syncState;
    /** @var LockManagerInterface */
    private $lockManager;
    /** @var Logger */
    private $logger;
    /** @var AsyncFailureRepository */
    private $asyncFailureRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OriginOfOrder $orderOrigin,
        AllegroOrderStatus $allegroOrderStatus,
        OutboundSyncState $syncState,
        LockManagerInterface $lockManager,
        Logger $logger,
        AsyncFailureRepository $asyncFailureRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderOrigin = $orderOrigin;
        $this->allegroOrderStatus = $allegroOrderStatus;
        $this->syncState = $syncState;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
        $this->asyncFailureRepository = $asyncFailureRepository;
    }

    public function process(EntityMessageInterface $message): void
    {
        $orderId = (int)$message->getEntityId();
        if ($orderId < 1) {
            return;
        }
        $lockName = 'macopedia_allegro_order_status_' . $orderId;
        if (!$this->lockManager->lock($lockName, 0)) {
            throw new \RuntimeException('Order status synchronization is already running.');
        }

        try {
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);
            if (!$this->orderOrigin->isOrderFromAllegro($order)) {
                return;
            }
            $targetStatus = $this->allegroOrderStatus->getTargetStatus($order);
            if ($targetStatus === null) {
                return;
            }
            $hash = $this->syncState->hash(['target_status' => $targetStatus]);
            if ($this->syncState->isCurrent(self::OPERATION, (string)$orderId, $hash)) {
                return;
            }
            if ($this->allegroOrderStatus->updateOrderStatus($order)) {
                $this->syncState->markCurrent(self::OPERATION, (string)$orderId, $hash);
            }
        } catch (\Throwable $exception) {
            $this->asyncFailureRepository->recordFailure(
                AsyncFailureRepository::OPERATION_ORDER_STATUS,
                $orderId,
                $exception
            );
            $this->logger->apiFailure('Allegro order status synchronization failed', [
                'order_id' => $orderId,
                'exception_type' => get_class($exception),
            ]);
            throw $exception;
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }
}
