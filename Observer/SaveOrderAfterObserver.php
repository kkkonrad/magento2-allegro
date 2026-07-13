<?php

namespace Macopedia\Allegro\Observer;

use Macopedia\Allegro\Model\OrderImporter\OriginOfOrder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Macopedia\Allegro\Service\OutboundMessagePublisher;

class SaveOrderAfterObserver implements ObserverInterface
{
    /**
     * @var OriginOfOrder
     */
    protected $orderOrigin;

    /**
     * @var AllegroOrderStatus
     */
    protected $messagePublisher;

    /**
     * @param OriginOfOrder $orderOrigin
     * @param OutboundMessagePublisher $messagePublisher
     */
    public function __construct(
        OriginOfOrder $orderOrigin,
        OutboundMessagePublisher $messagePublisher
    ) {
        $this->orderOrigin = $orderOrigin;
        $this->messagePublisher = $messagePublisher;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if (!$order->getId() || !$this->orderOrigin->isOrderFromAllegro($order)) {
            return $this;
        }
        $this->messagePublisher->publishOrderStatus((int)$order->getId());

        return $this;
    }
}
