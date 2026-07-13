<?php

namespace Macopedia\Allegro\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Macopedia\Allegro\Service\MessageQtyChange;

/**
 * Puts message in a queue after product stock change
 */
class OrderObserver implements ObserverInterface
{
    /** @var MessageQtyChange */
    private $messageQtyChange;

    /**
     * OrderObserver constructor.
     * @param MessageQtyChange $messageQtyChange
     */
    public function __construct(
        MessageQtyChange $messageQtyChange
    ) {
        $this->messageQtyChange = $messageQtyChange;
    }

    /**
     * @param Observer $observer
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order) {
            return;
        }

        $productIds = [];
        foreach ($order->getItems() as $item) {
            $productIds[(int)$item->getProductId()] = true;
        }
        foreach (array_keys($productIds) as $productId) {
            $this->messageQtyChange->execute($productId);
        }
    }
}
