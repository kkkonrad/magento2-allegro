<?php

namespace Macopedia\Allegro\Observer;

use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\OrderImporter\OriginOfOrder;
use Macopedia\Allegro\Service\OutboundMessagePublisher;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Shipment;

/**
 * Sends shipment to Allegro after save shipping event
 */
class SaveShippingAfterObserver implements ObserverInterface
{
    /** @var Configuration */
    private $config;

    /** @var OriginOfOrder */
    private $orderOrigin;

    /** @var OutboundMessagePublisher */
    private $messagePublisher;

    /**
     * SaveShippingAfterObserver constructor.
     * @param Configuration $config
     * @param OriginOfOrder $orderOrigin
     * @param OutboundMessagePublisher $messagePublisher
     */
    public function __construct(
        Configuration $config,
        OriginOfOrder $orderOrigin,
        OutboundMessagePublisher $messagePublisher
    ) {
        $this->config = $config;
        $this->orderOrigin = $orderOrigin;
        $this->messagePublisher = $messagePublisher;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->isTrackingNumberSendingEnabled()) {
            return;
        }

        /** @var Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();

        $order = $shipment->getOrder();

        if (!$this->orderOrigin->isOrderFromAllegro($order)) {
            return;
        }

        $this->messagePublisher->publishShipment((int)$shipment->getId());
    }
}
